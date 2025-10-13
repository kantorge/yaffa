<?php

namespace Tests\Unit\Listeners;

use Tests\TestCase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use App\Listeners\DataLayerEventForLoginSuccess;
use Illuminate\Auth\Events\Login;
use App\Models\User;

class DataLayerEventForLoginSuccessTest extends TestCase
{
    public function test_listener_is_triggered_on_login_event()
    {
        // Set GTM container ID to enable listener
        Config::set('yaffa.gtm_container_id', 'GTM-XXXXXX');

        // Create a mock user
        $user = new User(['email' => 'user@example.com']);

        // Create a Login event
        $event = new Login('web', $user, false);

        // Clear session dataLayer
        Session::forget('dataLayer');

        // Instantiate and handle the event
        $listener = new DataLayerEventForLoginSuccess();
        $listener->handle($event);

        // Assert that dataLayer is flashed to session
        $dataLayer = session()->get('dataLayer');
        $this->assertNotEmpty($dataLayer);
        $this->assertEquals('loginSuccess', $dataLayer[0]['event']);
        $this->assertFalse($dataLayer[0]['is_generic_demo_user']);
    }
}
