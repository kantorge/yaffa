<?php

it('runs function-style Pest tests on the Laravel TestCase', function () {
    $this->get('/login')->assertOk();
});
