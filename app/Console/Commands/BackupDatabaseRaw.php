<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\DbDumper\Databases\MySql;

/**
 * Writes a plain, uncompressed `.sql` dump straight to every backup disk —
 * deliberately not zipped, so opening a backup never needs an unzip step
 * first; the file you see in Admin → Backups (or on the D: drive) is the
 * exact dump. Reuses the same mysql connection / dump_binary_path config
 * spatie/laravel-backup already read (config/database.php), just skips its
 * archive-everything-into-one-zip step.
 */
class BackupDatabaseRaw extends Command
{
    protected $signature = 'backup:run-raw';

    protected $description = 'Dump the database straight to a plain .sql file on every backup disk (no zip), then prune old dumps.';

    public function handle(): int
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error("backup:run-raw only supports the mysql connection right now (current default connection [{$connectionName}] uses driver [".($connection['driver'] ?? 'unknown').']).');

            return self::FAILURE;
        }

        $filename = 'gold-shop-'.now()->format('Y-m-d-H-i-s').'.sql';
        $tempPath = storage_path('app/'.Str::random(12).'.sql');

        try {
            $dumper = MySql::create()
                ->setHost($connection['host'])
                ->setPort((int) $connection['port'])
                ->setDbName($connection['database'])
                ->setUserName($connection['username'])
                ->setPassword($connection['password']);

            if (! empty($connection['dump']['dump_binary_path'])) {
                $dumper->setDumpBinaryPath($connection['dump']['dump_binary_path']);
            }

            $dumper->dumpToFile($tempPath);
        } catch (\Throwable $e) {
            $this->error('Database dump failed: '.$e->getMessage());
            @unlink($tempPath);

            return self::FAILURE;
        }

        $written = [];
        foreach ($this->activeDisks() as $disk) {
            try {
                Storage::disk($disk)->put($filename, file_get_contents($tempPath));
                $written[] = $disk;
            } catch (\Throwable $e) {
                $this->warn("Could not write the backup to disk [{$disk}]: ".$e->getMessage());
            }
        }

        @unlink($tempPath);

        if ($written === []) {
            $this->error('The dump succeeded but could not be written to any backup disk.');

            return self::FAILURE;
        }

        $this->prune();

        $this->info("Backup written: {$filename} (".implode(', ', $written).')');

        return self::SUCCESS;
    }

    /** Every disk backups are written to on this install. */
    private function activeDisks(): array
    {
        return array_filter([
            'backups',
            config('filesystems.disks.backups_secondary.root') ? 'backups_secondary' : null,
        ]);
    }

    /** Delete .sql dumps past the retention window — reuses the same setting spatie/laravel-backup's config already had. */
    private function prune(): void
    {
        $days = (int) config('backup.cleanup.default_strategy.keep_all_backups_for_days', 7);
        $cutoff = now()->subDays($days)->timestamp;

        foreach ($this->activeDisks() as $disk) {
            try {
                foreach (Storage::disk($disk)->files() as $path) {
                    if (str_ends_with($path, '.sql') && Storage::disk($disk)->lastModified($path) < $cutoff) {
                        Storage::disk($disk)->delete($path);
                    }
                }
            } catch (\Throwable $e) {
                // Disk unreachable (drive unplugged, network path down, etc.) — skip pruning there, not fatal.
            }
        }
    }
}
