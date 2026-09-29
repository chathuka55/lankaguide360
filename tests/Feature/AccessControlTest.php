<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_home_page_with_navbar_and_footer(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Plan your perfect Sri Lanka journey')
            ->assertSee('Start Planning')
            ->assertSee('Quick links')
            ->assertSee('Privacy Policy');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicPages(): array
    {
        return [
            'home' => ['/'],
            'destinations' => ['/destinations'],
            'plan' => ['/plan'],
            'about' => ['/about'],
            'contact' => ['/contact'],
            'privacy' => ['/privacy'],
            'terms' => ['/terms'],
            'login' => ['/login'],
            'register' => ['/register'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_load_for_guests(string $uri): void
    {
        $this->get($uri)->assertOk()->assertSee('LankaGuide');
    }

    public function test_guest_is_redirected_from_admin_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_my_trips_to_login(): void
    {
        $this->get('/my-trips')->assertRedirect(route('login'));
    }

    public function test_traveller_gets_403_on_admin(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($traveller)->get('/admin')->assertForbidden();
    }

    public function test_agent_can_open_admin(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get('/admin')->assertOk()->assertSee('Travel Agent');
    }

    public function test_admin_can_open_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Dashboard');
    }

    public function test_traveller_can_open_my_trips(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($traveller)->get('/my-trips')->assertOk()->assertSee('No trips yet');
    }

    public function test_dashboard_redirects_by_role(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/my-trips');
        $this->actingAs(User::factory()->agent()->create())->get('/dashboard')->assertRedirect('/admin');
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertRedirect('/admin');
    }

    public function test_new_registrations_are_travellers_even_if_a_role_is_posted(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);

        $this->assertSame(UserRole::Traveller, User::where('email', 'sneaky@example.com')->sole()->role);
    }

    public function test_admin_only_menu_items_are_hidden_from_agents(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/admin')->assertDontSee('Import tools');
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSee('Import tools');
    }

    public function test_seeder_creates_admin_and_agent_logins(): void
    {
        $this->seed();

        $this->assertTrue(User::where('email', 'admin@lankaguide360.test')->sole()->isAdmin());
        $this->assertTrue(User::where('email', 'agent@lankaguide360.test')->sole()->isAgent());
    }
}
