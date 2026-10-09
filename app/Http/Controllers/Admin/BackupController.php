<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use League\Flysystem\FilesystemException;

/**
 * Database backups — fully automatic (see the nightly `backup:run-raw`
 * schedule in routes/console.php, App\Console\Commands\BackupDatabaseRaw),
 * written as a plain, uncompressed .sql file straight to "backups" (always)
 * and, only where BACKUP_SECOND_DRIVE_PATH is set for this install, also to
 * "backups_secondary" (config/filesystems.php) — a second physically separate
 * location, whatever that means on this particular machine. Deliberately not
 * zipped, so opening one never needs an unzip step first. No cloud step, so
 * it runs the same whether the shop is online or not. This screen is just
 * visibility into what already happened, plus a manual "back up now" for
 * peace of mind and a way to grab a copy for safekeeping elsewhere —
 * nothing here is required for backups to keep happening on their own.
 */
class BackupController extends Controller
{
    public function index()
    {
        $files = collect($this->activeDisks())->flatMap(function (string $label, string $disk) {
            return collect($this->filesOn($disk))
                // .sql is the current format; .zip is kept listed/downloadable
                // for any backup made before this switched away from zipping.
                ->filter(fn ($path) => str_ends_with($path, '.sql') || str_ends_with($path, '.zip'))
                ->map(fn ($path) => [
                    'disk' => $disk,
                    'location' => $label,
                    'path' => $path,
                    'filename' => basename($path),
                    'size_mb' => round(Storage::disk($disk)->size($path) / 1048576, 2),
                    'created_at' => Storage::disk($disk)->lastModified($path),
                ]);
        })->sortByDesc('created_at')->values();

        $primary = $files->where('disk', 'backups');
        $secondaryConfigured = (bool) config('filesystems.disks.backups_secondary.root');

        return Inertia::render('admin/backups/index', [
            'backups' => $files,
            'secondaryConfigured' => $secondaryConfigured,
            'secondaryAvailable' => $secondaryConfigured && $this->diskReachable('backups_secondary'),
            'totalSizeMb' => round($files->sum('size_mb'), 2),
            // "Are we actually safe" only needs the primary copy to be recent —
            // a second location, where one exists, is a bonus, not a requirement.
            'newestAgeHours' => $primary->isEmpty() ? null : round((time() - $primary->first()['created_at']) / 3600, 1),
        ]);
    }

    public function run()
    {
        $exitCode = Artisan::call('backup:run-raw');

        return $exitCode === 0
            ? back()->with('success', 'Backup created.')
            : back()->with('error', 'Backup failed — check the server log for details.');
    }

    public function download(string $disk, string $path)
    {
        abort_unless(array_key_exists($disk, $this->activeDisks()), 404);

        // URL-safe base64 (the raw path can contain "/" — a plain base64
        // value would break Laravel's single-segment route matching).
        $path = base64_decode(strtr($path, '-_', '+/'));

        // Only ever serve a path this exact disk listing produced — rules
        // out any path traversal from a tampered download link.
        abort_unless(in_array($path, $this->filesOn($disk), true), 404);

        return Storage::disk($disk)->download($path);
    }

    /** Every disk backups are written to on this install, with a human label for the UI. */
    private function activeDisks(): array
    {
        return array_filter([
            'backups' => 'This computer',
            'backups_secondary' => config('filesystems.disks.backups_secondary.root') ? 'Secondary location' : null,
        ]);
    }

    /** @return string[] */
    private function filesOn(string $disk): array
    {
        // The secondary disk can be unplugged, a not-yet-created folder, or a
        // network path that's temporarily gone — none of that should break
        // this page, it should just show that disk as empty/unavailable.
        try {
            return Storage::disk($disk)->allFiles();
        } catch (FilesystemException $e) {
            return [];
        }
    }

    /** Whether the disk's underlying drive/folder currently exists — independent of whether it has any backups on it yet. */
    private function diskReachable(string $disk): bool
    {
        $path = config("filesystems.disks.{$disk}.root");

        // The target folder may not exist yet on a brand new drive, but
        // something above it will — walk up until an existing ancestor is
        // found. Works for any path shape ("D:/Foo", "/mnt/foo", a UNC path).
        while ($path !== '' && ! is_dir($path)) {
            $parent = dirname($path);
            if ($parent === $path) {
                return false;
            }
            $path = $parent;
        }

        return is_dir($path);
    }
}
