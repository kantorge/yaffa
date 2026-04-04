<?php

namespace Tests\Browser\Pages\Partials;

use App\Models\User;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;
use Throwable;

const QUICK_ACTION_BAR_SELECTOR = '#quick-action-bar';

#[Group('extended')]
class QuickActionBarTest extends DuskTestCase
{
    /**
     * @throws Throwable
     */
    public function test_quick_action_bar_is_initially_hidden(): void
    {
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('home'))
                ->assertMissing(QUICK_ACTION_BAR_SELECTOR . ':not(.hidden)');
        });
    }

    /**
     * @throws Throwable
     */
    public function test_quick_action_bar_can_be_opened_and_closed(): void
    {
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit(route('home'))
                ->assertMissing(QUICK_ACTION_BAR_SELECTOR . ':not(.hidden)')
                ->click('@quick-action-bar-toggler')
                ->waitFor(QUICK_ACTION_BAR_SELECTOR)
                ->click('@quick-action-bar-close')
                ->waitUntilMissing(QUICK_ACTION_BAR_SELECTOR . ':not(.hidden)');
        });
    }
}
