<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoogleDriveConfig>
 */
class GoogleDriveConfigFactory extends Factory
{
    public function definition(): array
    {
        $projectId = 'yaffa-' . fake()->numberBetween(100000, 999999);
        $clientEmail = 'service-account-' . fake()->numberBetween(1000, 9999) . '@' . $projectId . '.iam.gserviceaccount.com';

        // Generate realistic service account JSON with all 8 required keys
        $serviceAccountJson = json_encode([
            'type' => 'service_account',
            'project_id' => $projectId,
            'private_key_id' => fake()->sha1(),
            'private_key' => "-----BEGIN PRIVATE KEY-----\n" . fake()->sha256() . "\n-----END PRIVATE KEY-----\n",
            'client_email' => $clientEmail,
            'client_id' => fake()->numerify('####################'),
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/' . urlencode($clientEmail),
        ]);

        return [
            'user_id' => User::factory(),
            'service_account_email' => $clientEmail,
            'service_account_json' => $serviceAccountJson,
            'folder_id' => fake()->regexify('[a-zA-Z0-9_-]{33}'), // Google Drive folder IDs are 33 characters
            'delete_after_import' => fake()->boolean(30), // 30% chance of true
            'enabled' => fake()->boolean(90), // 90% chance of enabled
            'last_sync_at' => fake()->optional(0.5)->dateTimeBetween('-7 days'),
            'last_error' => fake()->optional(0.2)->sentence(),
            'error_count' => fake()->numberBetween(0, 5),
        ];
    }

    /**
     * State for a config that has never synced
     */
    public function neverSynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_sync_at' => null,
            'last_error' => null,
            'error_count' => 0,
        ]);
    }

    /**
     * State for a config with recent sync errors
     */
    public function withErrors(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_error' => fake()->randomElement([
                'Failed to authenticate with Google Drive',
                'Folder not found or not accessible',
                'No delete permissions on folder',
                'Rate limit exceeded',
            ]),
            'error_count' => fake()->numberBetween(1, 10),
        ]);
    }

    /**
     * State for a disabled config
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    /**
     * State for a config with delete_after_import enabled
     */
    public function withDeleteAfterImport(): static
    {
        return $this->state(fn (array $attributes) => [
            'delete_after_import' => true,
        ]);
    }
}
