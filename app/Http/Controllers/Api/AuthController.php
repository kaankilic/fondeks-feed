<?php

namespace App\Http\Controllers\Api;

use App\Auth\ScryptHasher;
use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Token-based authentication for API consumers, backed by Laravel Sanctum.
 * The public market-data endpoints stay open; these guard everything else and
 * mint the personal access tokens a private client would send as
 * `Authorization: Bearer <token>`.
 */
class AuthController extends Controller
{
    use ApiResponses;

    /**
     * Create a password-based account and issue a Sanctum token. The password
     * is hashed with the same scrypt scheme the Next.js client uses so the
     * user can later sign in through either app.
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8|max:255',
                'name' => 'required|string|max:255',
                // Required consents must be an explicit `true` — `accepted`
                // covers true/1/"yes"/"on" so a checkbox form works too.
                'terms_accepted' => 'required|accepted',
                'kvkk_acknowledged' => 'required|accepted',
                'age_confirmed' => 'required|accepted',
                'marketing_consent' => 'sometimes|boolean',
                'device_name' => 'sometimes|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return $this->badRequest($e->validator->errors()->first());
        }

        $email = strtolower($request->input('email'));

        if (User::where('email', $email)->exists()) {
            return $this->badRequest('Bu e-posta ile bir hesap zaten mevcut.');
        }

        // One timestamp for the whole audit trail — all consents are recorded
        // at the same instant the account exists.
        $now = Carbon::now();

        $user = new User;
        $user->email = $email;
        $user->name = $request->input('name');
        $user->password_hash = (new ScryptHasher)->make($request->input('password'));
        $user->terms_accepted_at = $now;
        $user->kvkk_acknowledged_at = $now;
        $user->age_confirmed_at = $now;
        $user->marketing_consent_at = $request->boolean('marketing_consent') ? $now : null;
        $user->save();

        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        return $this->plain([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    /** Exchange e-mail + password for a personal access token. */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'device_name' => 'sometimes|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return $this->badRequest($e->validator->errors()->first());
        }

        $user = User::where('email', strtolower($request->input('email')))->first();

        if (! $user || ! (new ScryptHasher)->check($request->input('password'), $user->password_hash)) {
            return $this->unauthorizedResponse('Geçersiz e-posta veya şifre.');
        }

        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        return $this->plain([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Exchange a Google ID token (obtained client-side via Google Identity
     * Services) for a Sanctum token. New accounts are created on first sign-in
     * with a non-scrypt password_hash sentinel so password login stays blocked
     * for them until they set one. The `is_new_user` flag tells the frontend
     * to collect name/email via the existing POST /user endpoint.
     */
    public function googleCallback(Request $request)
    {
        try {
            $request->validate([
                'id_token' => 'required|string',
                // Required only when this request ends up creating the account;
                // see the new-user branch below. The frontend sends them from
                // the sign-in screen's consent checkboxes.
                'terms_accepted' => 'sometimes|accepted',
                'kvkk_acknowledged' => 'sometimes|accepted',
                'age_confirmed' => 'sometimes|accepted',
                'marketing_consent' => 'sometimes|boolean',
                'device_name' => 'sometimes|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return $this->badRequest($e->validator->errors()->first());
        }

        $clientId = config('services.google.client_id');
        if (! $clientId) {
            return response()->json(
                ['error' => 'Google OAuth is not configured.'],
                500,
                ['Cache-Control' => 'no-store'],
            );
        }

        try {
            $response = Http::timeout(5)
                ->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $request->input('id_token'),
                ]);
        } catch (ConnectionException) {
            return response()->json(
                ['error' => 'Google kimlik doğrulama servisine ulaşılamadı.'],
                502,
                ['Cache-Control' => 'no-store'],
            );
        }

        if (! $response->successful()) {
            return $this->unauthorizedResponse('Geçersiz Google kimlik tokenı.');
        }

        $payload = $response->json();

        if (! is_array($payload)
            || ! hash_equals((string) $clientId, (string) ($payload['aud'] ?? ''))
            || ($payload['email_verified'] ?? 'false') !== 'true'
            || empty($payload['email'])
            || empty($payload['sub'])
        ) {
            return $this->unauthorizedResponse('Geçersiz Google kimlik tokenı.');
        }

        $email = strtolower((string) $payload['email']);
        $user = User::where('email', $email)->first();
        $isNewUser = false;

        if (! $user) {
            // First-time Google sign-in doubles as signup, so the same three
            // consents the password-register path requires are required here.
            if (! $request->boolean('terms_accepted')
                || ! $request->boolean('kvkk_acknowledged')
                || ! $request->boolean('age_confirmed')
            ) {
                return $this->badRequest('Kullanım koşullarını, KVKK aydınlatma metnini ve 18 yaş onayını kabul etmelisiniz.');
            }

            $now = Carbon::now();

            $user = new User;
            $user->email = $email;
            $user->name = $payload['name'] ?? null;
            // Sentinel: ScryptHasher::check() rejects anything without the
            // `scrypt:` prefix, so password login is blocked until the user
            // sets one. The column is NOT NULL, so this cannot be empty.
            $user->password_hash = 'oauth:google';
            $user->terms_accepted_at = $now;
            $user->kvkk_acknowledged_at = $now;
            $user->age_confirmed_at = $now;
            $user->marketing_consent_at = $request->boolean('marketing_consent') ? $now : null;
            $user->save();
            $isNewUser = true;
        }

        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        return $this->plain([
            'token' => $token,
            'user' => $this->userPayload($user),
            'is_new_user' => $isNewUser,
        ]);
    }

    /** The authenticated token's owner. */
    public function me(Request $request)
    {
        return $this->plain(['user' => $this->userPayload($request->user())]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            ]);
        } catch (ValidationException $e) {
            return $this->badRequest($e->validator->errors()->first());
        }

        $user->name = $request->input('name');
        $user->save();

        return $this->plain(['user' => $this->userPayload($user)]);
    }

    /** Revoke the token used to make this request. */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->plain(['message' => 'logged out']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'termsAcceptedAt' => optional($user->terms_accepted_at)->toIso8601String(),
            'kvkkAcknowledgedAt' => optional($user->kvkk_acknowledged_at)->toIso8601String(),
            'ageConfirmedAt' => optional($user->age_confirmed_at)->toIso8601String(),
            'marketingConsentAt' => optional($user->marketing_consent_at)->toIso8601String(),
        ];
    }
}
