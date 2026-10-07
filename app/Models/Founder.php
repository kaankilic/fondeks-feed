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

    protected $fillable = ['name', 'initials', 'color', 'logo'];

    /** Memoised [name => stored logo path] for the request, loaded once. */
    private static ?array $storedLogos = null;

    public function funds()
    {
        return $this->hasMany(Fund::class, 'founder', 'name');
    }

    /**
     * Public-relative path of the founder's locally stored, squared logo (e.g.
     * "founder-logos/ak_portfoy.png"), or null when we hold none — in which case
     * callers fall back to the Fintables proxy and then the initials chip.
     *
     * Looks the name up in a per-request map of all founders that carry a logo,
     * so a fund list resolves every founder with a single query rather than one
     * per row. {@see \App\Console\Commands\FetchFounderLogos} populates the column.
     */
    public static function storedLogo(?string $name): ?string
    {
        if (self::$storedLogos === null) {
            self::$storedLogos = static::query()
                ->whereNotNull('logo')
                ->pluck('logo', 'name')
                ->all();
        }

        return $name === null ? null : (self::$storedLogos[$name] ?? null);
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
