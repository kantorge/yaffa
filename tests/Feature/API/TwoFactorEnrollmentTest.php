<?php

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwoFactorEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_disabled_by_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(route('api.v1.users.me.two-factor.show'));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['enabled' => false]);
    }

    public function test_enroll_then_confirm_enables_two_factor_and_returns_recovery_codes_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $enrollResponse = $this->postJson(route('api.v1.users.me.two-factor.enroll'));

        $enrollResponse->assertStatus(Response::HTTP_OK);
        $enrollResponse->assertJsonStructure(['secret', 'otpauth_uri', 'qr_svg']);

        $code = $user->fresh()->makeTwoFactorCode();

        $confirmResponse = $this->postJson(route('api.v1.users.me.two-factor.confirm'), [
            'code' => $code,
        ]);

        $confirmResponse->assertStatus(Response::HTTP_OK);
        $confirmResponse->assertJson(['enabled' => true]);
        $recoveryCodes = $confirmResponse->json('recovery_codes');
        $this->assertIsArray($recoveryCodes);
        $this->assertNotEmpty($recoveryCodes);

        $statusResponse = $this->getJson(route('api.v1.users.me.two-factor.show'));
        $statusResponse->assertJson(['enabled' => true]);
    }

    public function test_confirm_with_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->postJson(route('api.v1.users.me.two-factor.enroll'));

        $response = $this->postJson(route('api.v1.users.me.two-factor.confirm'), [
            'code' => '000000',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->getJson(route('api.v1.users.me.two-factor.show'))
            ->assertJson(['enabled' => false]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson(route('api.v1.users.me.two-factor.show'));

        $this->assertUserNotAuthorized($response);
    }
}
