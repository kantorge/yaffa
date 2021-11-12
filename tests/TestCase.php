<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected $base_route = null;
    protected $base_model = null;

    protected function setBaseRoute($route)
    {
        $this->base_route = $route;
    }

    protected function setBaseModel($model)
    {
        $this->base_model = $model;
    }

    protected function raw($class, $attributes = [], $times = null)
    {
        return $class::factory()->count($times)->raw($attributes);
    }

    protected function rawForUser(User $user, $class, $attributes = [], $times = null)
    {
        $attributes['user_id'] = $user->id;

        return $class::factory()->count($times)->raw($attributes);
    }

    protected function create($class, $attributes = [], $times = null)
    {
        return $class::factory()->count($times)->create($attributes);
    }

    protected function createForUser(User $user, $class, $attributes = [], $times = null)
    {
        return $class::factory()->for($user)->count($times)->create($attributes);
    }

    protected function assertCreateForUser(User $user, $attributes = [], $model = '', $route = '')
    {
        $route = $this->base_route ? "{$this->base_route}.store" : $route;
        $model = $this->base_model ?? $model;

        $attributes = $this->rawForUser($user, $model, $attributes);

        $response = $this->actingAs($user)->postJson(route($route), $attributes);
        $response->assertRedirect($this->base_route ? "{$this->base_route}" : $route);

        $model = new $model;

        $this->assertDatabaseHas($model->getTable(), $attributes);

        return $response;
    }

    protected function assertDestroyWithUser(User $user, $model = '', $route = '')
    {
        $route = $this->base_route ? "{$this->base_route}.destroy" : $route;
        $model = $this->base_model ?? $model;

        $model = $this->createForUser($user, $model);

        $response = $this->actingAs($user)->deleteJson(route($route, $model->id));

        $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);

        return $response;
    }
}
