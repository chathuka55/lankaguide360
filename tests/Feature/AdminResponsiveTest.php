<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin lists on iPad and small-laptop widths (Nov 2026): compact pagination that wraps and a
 * filter bar that wraps instead of squeezing the search box.
 */
class AdminResponsiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_lists_use_compact_pagination_and_a_wrapping_filter_bar(): void
    {
        Hotel::factory()->published()->count(300)->create(); // 12 pages of 25
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $html = $this->actingAs($admin)->get(route('admin.hotels.index', ['page' => 6]))
            ->assertOk()
            ->assertSee('Showing')
            ->assertSee('aria-label="Pagination"', false)
            ->assertSee('flex flex-wrap items-end gap-3', false)
            ->getContent();

        // One page each side of the current one, plus the ends: 1 2 … 5 [6] 7 … 11 12.
        foreach ([1, 2, 5, 7, 11, 12] as $page) {
            $this->assertStringContainsString('aria-label="Page '.$page.'"', $html);
        }
        foreach ([3, 4, 8, 9, 10] as $page) {
            $this->assertStringNotContainsString('aria-label="Page '.$page.'"', $html);
        }
    }
}
