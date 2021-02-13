<?php

/* tests\Utilities\functions.php */

function create($class, $attributes = [], $times = null)
{
    return $class::factory()->count($times)->create($attributes);
}
function make($class, $attributes = [], $times = null)
{
    return $class::factory()->count($times)->make($attributes);
}
function raw($class, $attributes = [], $times = null)
{
    return $class::factory()->count($times)->raw($attributes);
}
