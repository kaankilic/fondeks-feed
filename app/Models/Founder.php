<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /** The slug Fintables stores this founder's logo under. */
    public function logoSlug(): string
    {
        return static::logoSlugFor($this->name);
    }

    /**
     * The slug Fintables stores a founder's logo under, derived from the brand
     * portion of the name (everything up to "YÖNETİMİ"). Turkish letters are
     * folded by hand first, since the generic ASCII fold maps İ/ı wrongly.
     * e.g. "PUSULA PORTFÖY YÖNETİMİ A.Ş." → "pusula_portfoy".
     */
    public static function logoSlugFor(?string $name): string
    {
        return (string) Str::of((string) $name)
            ->before(' YÖNETİMİ')
            ->before(' YÖNETIMI')
            ->replace(
                ['ı', 'İ', 'ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'],
                ['i', 'i', 's', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c'],
            )
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }
}
