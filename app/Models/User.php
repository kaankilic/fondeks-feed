<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasUuids;

    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['email', 'name', 'password_hash'];
    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
