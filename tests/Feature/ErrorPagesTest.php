<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Friendly error pages (Nov 2026). The live 403s came from a traveller account opening /admin
 * after registering during planning; past 500s came from a missing build or table, so the error
 * pages must not depend on the Vite build.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_traveller_opening_the_admin_panel_gets_a_clear_page_and_can_switch_account(): void
    {
        $traveller = User::factory()->create(['name' => 'Nimal Traveller', 'role' => UserRole::Traveller]);

        $this->actingAs($traveller)->get('/admin/trips')
            ->assertForbidden()
            ->assertSee('This area is for LankaGuide360 staff')
            ->assertSee('Nimal Traveller')
            ->assertSee('Switch to a staff account')
            ->assertSee(route('switch-account'), false);

        $this->actingAs($traveller)->post(route('switch-account'), ['next' => '/admin/trips'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', url('/admin/trips'));
        $this->assertGuest();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(url('/admin/trips'));
    }

    public function test_switch_account_never_redirects_off_the_site(): void
    {
        $this->actingAs(User::factory()->create())->post(route('switch-account'), ['next' => '//evil.example/admin'])
            ->assertSessionHas('url.intended', route('admin.dashboard'));
    }

    public function test_other_403s_and_404s_use_the_branded_pages(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('couldn’t find that page', false)->assertSee('images/brand/mark-128.png', false);
    }

    public function test_the_500_page_needs_no_build_or_database(): void
    {
        config(['app.debug' => false]);
        Route::get('/_boom', fn () => throw new RuntimeException('secret stack detail'))->middleware('web');

        $this->get('/_boom')->assertStatus(500)
            ->assertSee('Something went wrong on our side')
            ->assertDontSee('/build/', false)
            ->assertDontSee('secret stack detail');
    }
}
