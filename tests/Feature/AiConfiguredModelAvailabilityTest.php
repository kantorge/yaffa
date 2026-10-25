<?php

use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

it('tells the user to pick another model when the configured model was removed', function () {
    config(['ai-documents.providers.openai.models' => ['listed-model']]);
    $user = User::factory()->create();
    AiProviderConfig::factory()->for($user)->create(['provider' => 'openai', 'model' => 'retired-model']);
    Sanctum::actingAs($user, ['*']);

    $this
        ->postJson(route('api.v1.imports.file-profiles.suggest'), [
            'file' => UploadedFile::fake()->createWithContent('sample.csv', "Date,Amount\n2025-01-01,1"),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'retired-model'));
});

it('reports a retired model as availability false and a listed one as true', function () {
    config(['ai-documents.providers.openai.models' => ['listed-model']]);

    expect(new AiProviderConfig(['provider' => 'openai', 'model' => 'retired-model']))->isModelAvailable()->toBeFalse()
        ->and(new AiProviderConfig(['provider' => 'openai', 'model' => 'listed-model']))->isModelAvailable()->toBeTrue();
});
