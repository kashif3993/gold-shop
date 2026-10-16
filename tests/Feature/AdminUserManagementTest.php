<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
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

    public function test_shopkeeper_is_forbidden_from_the_users_screen(): void
    {
        $this->actingAs($this->shopkeeper)->get('/admin/users')->assertForbidden();
        $this->actingAs($this->shopkeeper)->post("/admin/users/{$this->admin->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->actingAs($this->shopkeeper)->post("/admin/users/{$this->admin->id}/toggle-active")->assertForbidden();
    }

    public function test_admin_can_view_the_user_list(): void
    {
        $this->actingAs($this->admin)->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/index')
                ->has('users', 2));
    }

    public function test_admin_can_promote_a_shopkeeper_to_admin(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->shopkeeper->id}/role", ['role' => 'admin'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('admin', $this->shopkeeper->fresh()->role);
    }

    public function test_admin_can_demote_another_admin_to_shopkeeper(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin)->post("/admin/users/{$otherAdmin->id}/role", ['role' => 'shopkeeper'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('shopkeeper', $otherAdmin->fresh()->role);
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->admin->id}/role", ['role' => 'shopkeeper'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_role_must_be_a_valid_value(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->shopkeeper->id}/role", ['role' => 'superuser'])
            ->assertSessionHasErrors('role');
    }

    public function test_admin_can_deactivate_and_reactivate_another_user(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->shopkeeper->id}/toggle-active")
            ->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($this->shopkeeper->fresh()->is_active);

        $this->actingAs($this->admin)->post("/admin/users/{$this->shopkeeper->id}/toggle-active")
            ->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($this->shopkeeper->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->admin->id}/toggle-active")
            ->assertRedirect()->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->is_active);
    }
}
