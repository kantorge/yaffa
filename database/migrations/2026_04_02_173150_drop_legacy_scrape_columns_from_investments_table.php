<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (Schema::hasColumn('investments', 'scrape_url')) {
                $table->dropColumn('scrape_url');
            }

            if (Schema::hasColumn('investments', 'scrape_selector')) {
                $table->dropColumn('scrape_selector');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (! Schema::hasColumn('investments', 'scrape_url')) {
                $table->string('scrape_url')->nullable()->after('currency_id');
            }

            if (! Schema::hasColumn('investments', 'scrape_selector')) {
                $table->string('scrape_selector')->nullable()->after('scrape_url');
            }
        });

        DB::table('investments')
            ->select(['id', 'provider_settings'])
            ->where('investment_price_provider', 'web_scraping')
            ->orderBy('id')
            ->chunkById(200, function ($investments): void {
                foreach ($investments as $investment) {
                    $settings = json_decode((string) ($investment->provider_settings ?? 'null'), true);
                    if (! is_array($settings)) {
                        continue;
                    }

                    $url = isset($settings['url']) && is_string($settings['url'])
                        ? $settings['url']
                        : null;
                    $selector = isset($settings['selector']) && is_string($settings['selector'])
                        ? $settings['selector']
                        : null;

                    DB::table('investments')
                        ->where('id', $investment->id)
                        ->update([
                            'scrape_url' => $url,
                            'scrape_selector' => $selector,
                        ]);
                }
            });
    }
};
