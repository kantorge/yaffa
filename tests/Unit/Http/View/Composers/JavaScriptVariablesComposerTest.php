<?php

namespace Tests\Unit\Http\View\Composers;

use App\Enums\TransactionType;
use App\Http\View\Composers\JavaScriptVariablesComposer;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;
use Tests\TestCase;

class JavaScriptVariablesComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_payload_contains_only_config_values(): void
    {
        config(['yaffa.sandbox_mode' => true]);

        JavaScriptFacade::shouldReceive('put')
            ->once()
            ->withArgs(fn (array $payload): bool => array_key_exists('YAFFA', $payload)
                && $payload['YAFFA']['config']['sandbox_mode'] === true
                && $payload['YAFFA']['config']['transactionTypes'] === TransactionType::all()
                && array_key_exists('datePresets', $payload['YAFFA']['config'])
                && array_key_exists('translations', $payload['YAFFA']['config'])
                && $payload['YAFFA']['userSettings'] === []);

        $composer = new JavaScriptVariablesComposer();
        $composer->compose();
    }

    public function test_authenticated_payload_contains_user_values(): void
    {
        $user = User::factory()->create();
        Currency::factory()->for($user)->create(['base' => true]);

        $this->actingAs($user);

        JavaScriptFacade::shouldReceive('put')
            ->once()
            ->withArgs(function (array $payload) use ($user): bool {
                if (!array_key_exists('YAFFA', $payload)) {
                    return false;
                }

                $userPayload = $payload['YAFFA']['userSettings'] ?? null;
                if (!$userPayload) {
                    return false;
                }

                return $userPayload['language'] === $user->language
                    && $userPayload['locale'] === $user->locale
                    && $userPayload['baseCurrency'] instanceof Currency;
            });

        $composer = new JavaScriptVariablesComposer();
        $composer->compose();
    }
}
