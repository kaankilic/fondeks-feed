<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryPerformance extends Model
{
    protected $table = 'category_performance';
    protected $primaryKey = 'category';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['category', 'y1'];

    protected function casts(): array
    {
        return [
            'y1' => 'float',
        ];
    }
}
