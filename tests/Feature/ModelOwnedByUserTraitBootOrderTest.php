<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults user_id to the authenticated user even if the model booted before anyone logged in', function () {
    // A model boots once per process: boot Tag while nobody is authenticated
    Model::clearBootedModels();
    new Tag();

    $user = User::factory()->create();
    $this->actingAs($user);

    expect(Tag::create(['name' => 'Owned tag'])->user_id)->toBe($user->id);
});
