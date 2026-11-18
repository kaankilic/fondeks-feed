<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'email',
        'name',
        'password_hash',
        'terms_accepted_at',
        'kvkk_acknowledged_at',
        'age_confirmed_at',
        'marketing_consent_at',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'kvkk_acknowledged_at' => 'datetime',
            'age_confirmed_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /** The users table (shared with the Next.js app) has no remember_token column. */
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id');
    }
}
