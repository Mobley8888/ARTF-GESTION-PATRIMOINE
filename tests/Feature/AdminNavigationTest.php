<?php

namespace Tests\Feature;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_the_administrative_navigation(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('Biens patrimoniaux')
            ->assertSee(route('assets.index'));
    }

    public function test_administrative_sections_are_reachable(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));

        $sections = [
            'assets.index' => 'Biens patrimoniaux',
            'inventories.index' => 'Inventaires',
            'assignments.index' => 'Affectations',
            'maintenance.index' => 'Maintenance',
            'reports.index' => 'Rapports',
        ];

        foreach ($sections as $routeName => $title) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee($title);
        }
    }
}
