<?php

namespace App\Models;

use App\Http\Traits\ModelOwnedByUserTrait;
use App\Services\PublicEndpointUrlValidator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $model
 * @property string $api_key
 * @property string|null $base_url
 * @property bool $vision_enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @method static \Database\Factories\AiProviderConfigFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereVisionEnabled($value)
 * @mixin \Eloquent
 */
#[Hidden('api_key')]
#[Fillable('provider', 'model', 'api_key', 'base_url', 'vision_enabled')]
class AiProviderConfig extends Model
{
    use HasFactory;
    use ModelOwnedByUserTrait;

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'vision_enabled' => 'boolean',
        ];
    }

    /**
     * Whether the configured model is still listed in config/ai-documents.php.
     * Models flagged `supported => false` are still listed, so they keep working until removed.
     */
    public function isModelAvailable(): bool
    {
        if (config('ai-documents.providers.' . $this->provider . '.custom_models', false)) {
            return true;
        }

        $models = config('ai-documents.providers.' . $this->provider . '.models', []);

        return array_is_list($models)
            ? in_array($this->model, $models, true)
            : array_key_exists($this->model, $models);
    }

    /** Prism driver name; `openai_compatible` runs on the `openai` driver with a custom URL. */
    public function prismProvider(): string
    {
        return $this->provider === 'openai_compatible' ? 'openai' : $this->provider;
    }

    /**
     * @return array<string, string>
     */
    public function prismConfig(): array
    {
        return self::buildPrismConfig($this->provider, $this->api_key, $this->base_url);
    }

    /**
     * Re-validates the URL at send time (it may have been rebound since it was saved).
     * ponytail: no connection pinning through Prism's client; add curl pinning if rebinding becomes a concern.
     *
     * @return array<string, string>
     */
    public static function buildPrismConfig(string $provider, string $apiKey, ?string $baseUrl): array
    {
        $config = ['api_key' => $apiKey];

        if ($provider === 'openai_compatible' && $baseUrl !== null) {
            PublicEndpointUrlValidator::assertPublic($baseUrl, (bool) config('yaffa.ai_allow_private_endpoints'));
            $config['url'] = $baseUrl;
        }

        return $config;
    }

    public function unavailableModelMessage(): string
    {
        return "The AI model \"{$this->model}\" is no longer available. Please select a different model in your AI provider settings.";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
