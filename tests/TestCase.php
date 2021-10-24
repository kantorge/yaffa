<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

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

    /**
     * If true, setup has run at least once.
     * @var bool
     */
    protected static $setUpHasRunOnce = false;

    public function setUp() :void
    {
        parent::setUp();

        if (! static::$setUpHasRunOnce) {
            Artisan::call('migrate:fresh', ['--database' => env('DB_CONNECTION')]);
            Artisan::call('db:seed', ['--class' => 'TestSeeder']);

            static::$setUpHasRunOnce = true;
        }
    }

    protected function create($attributes = [], $model = '', $route = '')
    {
        $route = $this->base_route ? "{$this->base_route}.store" : $route;
        $model = $this->base_model ?? $model;

        $attributes = raw($model, $attributes);

        $response = $this->postJson(route($route), $attributes);
        $response->assertRedirect($this->base_route ? "{$this->base_route}" : $route);

        $model = new $model;

        $this->assertDatabaseHas($model->getTable(), $attributes);

        return $response;
    }

    protected function destroy($model = '', $route = '')
    {
        $route = $this->base_route ? "{$this->base_route}.destroy" : $route;
        $model = $this->base_model ?? $model;

        $model = create($model);

        $response = $this->deleteJson(route($route, $model->id));

        $this->assertDatabaseMissing($model->getTable(), $model->toArray());

        return $response;
    }
}
