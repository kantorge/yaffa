<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('investments')
            ->select(['id', 'provider_settings', 'scrape_url', 'scrape_selector'])
            ->where('investment_price_provider', 'web_scraping')
            ->orderBy('id')
            ->chunkById(200, function ($investments): void {
                foreach ($investments as $investment) {
                    $settings = json_decode((string) ($investment->provider_settings ?? 'null'), true);
                    $settings = is_array($settings) ? $settings : [];

                    $hasChanges = false;

                    if (
                        (! isset($settings['url']) || $settings['url'] === '')
                        && is_string($investment->scrape_url)
                        && $investment->scrape_url !== ''
                    ) {
                        $settings['url'] = $investment->scrape_url;
                        $hasChanges = true;
                    }

                    if (
                        (! isset($settings['selector']) || $settings['selector'] === '')
                        && is_string($investment->scrape_selector)
                        && $investment->scrape_selector !== ''
                    ) {
                        $settings['selector'] = $investment->scrape_selector;
                        $hasChanges = true;
                    }

                    if (! $hasChanges) {
                        continue;
                    }

                    $encodedSettings = json_encode($settings);
                    if ($encodedSettings === false) {
                        throw new RuntimeException(sprintf(
                            'Failed to encode provider_settings for investment id %d: %s',
                            (int) $investment->id,
                            json_last_error_msg()
                        ));
                    }

                    DB::table('investments')
                        ->where('id', $investment->id)
                        ->update(['provider_settings' => $encodedSettings]);
                }
            });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: this migration only normalizes existing data into provider_settings.
    }
};
