<?php

namespace App\Http\Controllers\Api;

use App\Auth\ScryptHasher;
use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
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

    /** The authenticated token's owner. */
    public function me(Request $request)
    {
        return $this->plain(['user' => $this->userPayload($request->user())]);
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
        ];
    }
}
