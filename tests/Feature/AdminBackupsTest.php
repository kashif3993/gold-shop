<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBackupsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopkeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->shopkeeper = User::factory()->create();

        // Unconfigured by default — matches a fresh install on any machine,
        // where "a second drive" isn't assumed to exist.
        config(['filesystems.disks.backups_secondary.root' => null]);
    }

    public function test_shopkeeper_is_forbidden_from_backups(): void
    {
        $this->actingAs($this->shopkeeper)->get('/admin/backups')->assertForbidden();
        $this->actingAs($this->shopkeeper)->post('/admin/backups/run')->assertForbidden();
    }

    public function test_admin_can_view_the_backups_list(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('Zar & Noor/2026-09-20-23-30-00.zip', 'dummy zip contents');

        $this->actingAs($this->admin)->get('/admin/backups')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/backups/index')
                ->has('backups', 1)
                ->where('backups.0.filename', '2026-09-20-23-30-00.zip')
                ->where('backups.0.disk', 'backups')
                ->where('backups.0.location', 'This computer'));
    }

    public function test_secondary_location_is_not_offered_when_unconfigured(): void
    {
        Storage::fake('backups');

        $this->actingAs($this->admin)->get('/admin/backups')
            ->assertInertia(fn ($page) => $page
                ->where('secondaryConfigured', false)
                ->where('secondaryAvailable', false));
    }

    public function test_backups_list_merges_both_disks_when_a_secondary_is_configured(): void
    {
        // Storage::fake() swaps in a fake disk without touching config, but
        // "configured" here is read straight from config (mirroring how
        // BACKUP_SECOND_DRIVE_PATH is actually read in production) — so a
        // real temp directory is used instead, to exercise that check for
        // real rather than faking around it.
        $secondaryRoot = sys_get_temp_dir().'/gold-shop-test-secondary-'.uniqid();
        config(['filesystems.disks.backups_secondary.root' => $secondaryRoot]);
        Storage::fake('backups');
        Storage::disk('backups')->put('Zar & Noor/2026-09-20-23-30-00.zip', 'dummy zip contents');
        Storage::disk('backups_secondary')->put('Zar & Noor/2026-09-20-23-30-00.zip', 'dummy zip contents');

        try {
            $this->actingAs($this->admin)->get('/admin/backups')
                ->assertInertia(fn ($page) => $page
                    ->where('secondaryConfigured', true)
                    ->where('secondaryAvailable', true)
                    ->has('backups', 2)
                    ->where('backups.1.disk', 'backups_secondary')
                    ->where('backups.1.location', 'Secondary location'));
        } finally {
            File::deleteDirectory($secondaryRoot);
        }
    }

    public function test_backups_list_is_empty_when_none_exist_yet(): void
    {
        Storage::fake('backups');

        $this->actingAs($this->admin)->get('/admin/backups')
            ->assertInertia(fn ($page) => $page
                ->has('backups', 0)
                ->where('newestAgeHours', null));
    }

    public function test_missing_secondary_drive_does_not_break_the_page(): void
    {
        // Configured, but pointing at a path that can't exist right now —
        // what a genuinely unplugged/missing drive looks like. The page
        // should degrade gracefully rather than 500.
        config(['filesystems.disks.backups_secondary.root' => 'Z:/does-not-exist']);
        Storage::fake('backups');

        $this->actingAs($this->admin)->get('/admin/backups')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('backups', 0)
                ->where('secondaryConfigured', true)
                ->where('secondaryAvailable', false));
    }

    public function test_admin_can_trigger_a_backup_now(): void
    {
        // The real dump shells out to mysqldump and writes to every
        // configured disk — not something a unit test should depend on.
        // What this route promises is "run the artisan command and report
        // whether it succeeded", so that's what's tested.
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run-raw')
            ->andReturn(0);

        $this->actingAs($this->admin)->post('/admin/backups/run')
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_backup_run_reports_failure(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(1);

        $this->actingAs($this->admin)->post('/admin/backups/run')
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_admin_can_download_a_listed_backup_from_either_disk(): void
    {
        $secondaryRoot = sys_get_temp_dir().'/gold-shop-test-secondary-'.uniqid();
        config(['filesystems.disks.backups_secondary.root' => $secondaryRoot]);
        Storage::fake('backups');
        Storage::disk('backups')->put('Zar & Noor/2026-09-20-23-30-00.zip', 'dummy zip contents');
        Storage::disk('backups_secondary')->put('Zar & Noor/2026-09-20-23-30-00.zip', 'dummy zip contents');
        $encoded = strtr(base64_encode('Zar & Noor/2026-09-20-23-30-00.zip'), '+/', '-_');

        try {
            $this->actingAs($this->admin)->get("/admin/backups/backups/{$encoded}/download")->assertOk();
            $this->actingAs($this->admin)->get("/admin/backups/backups_secondary/{$encoded}/download")->assertOk();
        } finally {
            File::deleteDirectory($secondaryRoot);
        }
    }

    public function test_download_rejects_a_path_the_disk_listing_never_produced(): void
    {
        Storage::fake('backups');
        $encoded = strtr(base64_encode('../../.env'), '+/', '-_');

        $this->actingAs($this->admin)->get("/admin/backups/backups/{$encoded}/download")
            ->assertNotFound();
    }

    public function test_download_rejects_an_unknown_disk(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('a.zip', 'x');
        $encoded = strtr(base64_encode('a.zip'), '+/', '-_');

        $this->actingAs($this->admin)->get("/admin/backups/s3/{$encoded}/download")
            ->assertNotFound();
    }

    public function test_download_rejects_the_secondary_disk_when_unconfigured(): void
    {
        Storage::fake('backups_secondary');
        Storage::disk('backups_secondary')->put('a.zip', 'x');
        $encoded = strtr(base64_encode('a.zip'), '+/', '-_');

        // Faking a disk repoints its root even if the app considers it
        // "unconfigured" — the route must still refuse it based on the
        // actual BACKUP_SECOND_DRIVE_PATH setting, not just disk existence.
        config(['filesystems.disks.backups_secondary.root' => null]);

        $this->actingAs($this->admin)->get("/admin/backups/backups_secondary/{$encoded}/download")
            ->assertNotFound();
    }
}
