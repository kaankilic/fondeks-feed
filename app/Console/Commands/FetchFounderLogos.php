<?php

namespace App\Console\Commands;

use App\Models\Founder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Downloads each fund founder's (kurucu) logo from Fintables' public storage,
 * squares it onto a transparent canvas and stores it under
 * public/founder-logos, then records the stored path on the founder row. The
 * API and admin then serve the logo as a static asset from our own origin,
 * falling back to the live proxy (and finally the initials chip) for founders
 * Fintables has no logo for.
 *
 *   php artisan founders:fetch-logos
 *   php artisan founders:fetch-logos --force   # re-square even if the file exists
 */
class FetchFounderLogos extends Command
{
    protected $signature = 'founders:fetch-logos {--force : Re-download and re-square logos that already exist on disk}';

    protected $description = "Download, square and store fund founder logos, and record them on the founders table";

    private const UPSTREAM = 'https://storage.fintables.com/media/uploads/fund-management-logos/';

    /** Side length of the square canvas, in pixels. */
    private const SIZE = 256;

    /** Where the squared logos live, relative to the public directory. */
    private const DIR = 'founder-logos';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('The GD extension is required to square the logos.');

            return self::FAILURE;
        }

        $dir = public_path(self::DIR);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Could not create {$dir}.");

            return self::FAILURE;
        }

        $founders = Founder::orderBy('name')->get();
        $this->info("Fetching logos for {$founders->count()} founders…");

        $stored = 0;
        $missing = 0;

        foreach ($founders as $founder) {
            $slug = $founder->logoSlug();
            if ($slug === '') {
                $this->line("  <fg=yellow>skip</>  {$founder->name} (no slug)");
                continue;
            }

            $relative = self::DIR.'/'.$slug.'.png';
            $path = public_path($relative);

            if (! $this->option('force') && is_file($path)) {
                // Already squared on a previous run; just make sure the row points at it.
                $this->persist($founder, $relative);
                $stored++;
                $this->line("  <fg=green>have</>  {$founder->name}");
                continue;
            }

            $source = $this->download($slug);
            if ($source === null) {
                // No logo upstream — clear any stale path so the UI falls back.
                $this->persist($founder, null);
                $missing++;
                $this->line("  <fg=gray>none</>  {$founder->name}");
                continue;
            }

            if (! $this->square($source, $path)) {
                $this->persist($founder, null);
                $missing++;
                $this->line("  <fg=red>fail</>  {$founder->name} (unreadable image)");
                continue;
            }

            $this->persist($founder, $relative);
            $stored++;
            $this->line("  <fg=green>save</>  {$founder->name} -> {$relative}");
        }

        $this->newline();
        $this->info("Done. {$stored} stored, {$missing} without a logo.");

        return self::SUCCESS;
    }

    /** Raw image bytes from the CDN, or null when there is no usable logo. */
    private function download(string $slug): ?string
    {
        try {
            $response = Http::timeout(15)->get(self::UPSTREAM.$slug.'_logo.png');
        } catch (\Throwable) {
            return null;
        }

        $type = (string) $response->header('Content-Type');

        if (! $response->successful() || $response->body() === '' || ! str_starts_with($type, 'image/')) {
            return null;
        }

        return $response->body();
    }

    /**
     * Centre the source image on a transparent SIZE×SIZE canvas, preserving its
     * aspect ratio (and never upscaling past its native resolution), and write
     * it out as a PNG. Returns false when the bytes aren't a decodable image.
     */
    private function square(string $bytes, string $path): bool
    {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return false;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);

        $scale = min(self::SIZE / $sw, self::SIZE / $sh, 1.0);
        $dw = (int) max(1, round($sw * $scale));
        $dh = (int) max(1, round($sh * $scale));
        $dx = intdiv(self::SIZE - $dw, 2);
        $dy = intdiv(self::SIZE - $dh, 2);

        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, self::SIZE, self::SIZE, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);

        $ok = imagepng($dst, $path);

        imagedestroy($src);
        imagedestroy($dst);

        return $ok;
    }

    /** Records (or clears) the stored logo path without touching timestamps. */
    private function persist(Founder $founder, ?string $relative): void
    {
        if ($founder->logo !== $relative) {
            $founder->logo = $relative;
            $founder->save();
        }
    }
}
