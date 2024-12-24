<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Http\Controllers\API\OnboardingApiController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class OnboardingApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->controller = new OnboardingApiController();
    }

    public function testGetOnboardingDataReturnsCorrectData()
    {
        $request = new Request();
        $request->setUserResolver(function () {
            $user = new User();
            $user->id = 1;
            return $user;
        });

        $response = $this->controller->getOnboardingData($request, 'dashboard');

        $this->assertEquals(Response::HTTP_OK, $response->status());
        $this->assertArrayHasKey('dismissed', $response->getData(true));
        $this->assertArrayHasKey('steps', $response->getData(true));
    }

    public function testSetDismissedFlagSetsCorrectFlag()
    {
        $request = new Request();
        $request->setUserResolver(function () {
            $user = new User();
            $user->id = 1;
            return $user;
        });

        $response = $this->controller->setDismissedFlag($request, 'dashboard');

        $this->assertEquals(Response::HTTP_OK, $response->status());
        $this->assertTrue($request->user()->hasFlag('dismissOnboardingWidgetDashboard'));
    }

    public function testSetCompletedTourFlagSetsCorrectFlag()
    {
        $request = new Request();
        $request->setUserResolver(function () {
            $user = new User();
            $user->id = 1;
            return $user;
        });

        $response = $this->controller->setCompletedTourFlag($request, 'dashboard');

        $this->assertEquals(Response::HTTP_OK, $response->status());
        $this->assertTrue($request->user()->hasFlag('viewProductTour-dashboard'));
    }
}
