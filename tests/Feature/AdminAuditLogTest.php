<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopkeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->shopkeeper = User::factory()->create();
    }

    private function makeLog(array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'entity_type' => 'rate',
            'entity_id' => 1,
            'field_name' => 'usd_pkr',
            'old_value' => '280',
            'new_value' => '285',
            'reason' => 'USD→PKR rate updated',
            'changed_by_user_id' => $this->admin->id,
        ], $overrides));
    }

    public function test_shopkeeper_is_forbidden_from_the_audit_log(): void
    {
        $this->actingAs($this->shopkeeper)->get('/admin/audit-log')->assertForbidden();
    }

    public function test_admin_can_view_the_audit_log(): void
    {
        $this->makeLog();
        $this->makeLog(['entity_type' => 'discount', 'field_name' => 'total_discount']);

        $this->actingAs($this->admin)->get('/admin/audit-log')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/audit-log/index')
                ->has('logs.data', 2)
                ->has('entityTypes'));
    }

    public function test_audit_log_filters_by_entity_type(): void
    {
        $this->makeLog(['entity_type' => 'rate']);
        $this->makeLog(['entity_type' => 'discount']);

        $this->actingAs($this->admin)->get('/admin/audit-log?entity_type=discount')
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.entity_type', 'discount'));
    }

    public function test_audit_log_filters_by_date_range(): void
    {
        $this->makeLog()->forceFill(['changed_at' => '2026-01-01 10:00:00'])->save();
        $this->makeLog();

        $this->actingAs($this->admin)->get('/admin/audit-log?date_from=2026-06-01')
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_audit_log_shows_who_made_the_change(): void
    {
        $this->makeLog();

        $this->actingAs($this->admin)->get('/admin/audit-log')
            ->assertInertia(fn ($page) => $page->where('logs.data.0.changed_by.username', $this->admin->username));
    }
}
