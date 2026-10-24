<?php

use App\Models\AccountGroup;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not overwrite an explicit user_id with the authenticated user', function () {
    $this->actingAs(User::factory()->create());
    $otherUser = User::factory()->create();

    expect(AccountGroup::factory()->for($otherUser)->create()->user_id)->toBe($otherUser->id);
});

it('defaults user_id to the authenticated user when unset', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $accountGroup = AccountGroup::factory()->make(['user_id' => null]);
    $accountGroup->save();

    expect($accountGroup->user_id)->toBe($user->id);
});

it('defaults user_id to the authenticated user even if the model booted before anyone logged in', function () {
    // A model boots once per process: boot Tag while nobody is authenticated
    Model::clearBootedModels();
    new Tag();

    $user = User::factory()->create();
    $this->actingAs($user);

    expect(Tag::create(['name' => 'Owned tag'])->user_id)->toBe($user->id);
});
