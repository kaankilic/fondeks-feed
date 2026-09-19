<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Founder extends Model
{
    protected $table = 'founders';
    protected $primaryKey = 'name';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['name', 'initials', 'color'];

    public function funds()
    {
        return $this->hasMany(Fund::class, 'founder', 'name');
    }
}
