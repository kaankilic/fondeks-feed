<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guide extends Model
{
    protected $table = 'guides';
    protected $primaryKey = 'slug';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['slug', 'title', 'summary', 'category', 'reading_minutes', 'body', 'published_at'];

    protected function casts(): array
    {
        return [
            'reading_minutes' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
