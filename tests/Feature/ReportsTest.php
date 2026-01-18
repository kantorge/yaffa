<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_report_page_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/tax');

        $response->assertStatus(200);
        $response->assertViewIs('reports.tax');
    }

    public function test_unrealised_interest_report_page_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/unrealised-interest');

        $response->assertStatus(200);
        $response->assertViewIs('reports.unrealised-interest');
    }

    public function test_tax_report_requires_authentication(): void
    {
        $response = $this->get('/reports/tax');

        $response->assertRedirect('/login');
    }

    public function test_unrealised_interest_requires_authentication(): void
    {
        $response = $this->get('/reports/unrealised-interest');

        $response->assertRedirect('/login');
    }
}
