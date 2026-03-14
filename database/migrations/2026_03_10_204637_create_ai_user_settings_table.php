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
        $defaultSettings = $this->defaultSettings();

        Schema::create('ai_user_settings', function (Blueprint $table) use ($defaultSettings) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->boolean('ai_enabled')->default($defaultSettings['ai_enabled']);
            $table->string('ocr_language', 64)->default($defaultSettings['ocr_language']);
            $table->unsignedSmallInteger('image_max_width_vision')->default($defaultSettings['image_max_width_vision']);
            $table->unsignedSmallInteger('image_max_height_vision')->default($defaultSettings['image_max_height_vision']);
            $table->unsignedTinyInteger('image_quality_vision')->default($defaultSettings['image_quality_vision']);
            $table->unsignedSmallInteger('image_max_width_tesseract')->nullable();
            $table->unsignedSmallInteger('image_max_height_tesseract')->nullable();
            $table->decimal('asset_similarity_threshold', 4, 3)->default($defaultSettings['asset_similarity_threshold']);
            $table->unsignedTinyInteger('asset_max_suggestions')->default($defaultSettings['asset_max_suggestions']);
            $table->decimal('match_auto_accept_threshold', 4, 3)->default($defaultSettings['match_auto_accept_threshold']);
            $table->unsignedTinyInteger('duplicate_date_window_days')->default($defaultSettings['duplicate_date_window_days']);
            $table->decimal('duplicate_amount_tolerance_percent', 5, 2)->default($defaultSettings['duplicate_amount_tolerance_percent']);
            $table->decimal('duplicate_similarity_threshold', 4, 3)->default($defaultSettings['duplicate_similarity_threshold']);
            $table->string('category_matching_mode', 32)->default($defaultSettings['category_matching_mode']);
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($defaultSettings, $timestamp) {
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = array_merge(
                        ['user_id' => $user->id],
                        $defaultSettings,
                        [
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]
                    );
                }

                if ($rows !== []) {
                    DB::table('ai_user_settings')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_user_settings');
    }

    private function defaultSettings(): array
    {
        return [
            'ai_enabled' => false,
            'ocr_language' => 'eng',
            'image_max_width_vision' => 2048,
            'image_max_height_vision' => 2048,
            'image_quality_vision' => 85,
            'image_max_width_tesseract' => null,
            'image_max_height_tesseract' => null,
            'asset_similarity_threshold' => 0.5,
            'asset_max_suggestions' => 10,
            'match_auto_accept_threshold' => 0.95,
            'duplicate_date_window_days' => 3,
            'duplicate_amount_tolerance_percent' => 10.0,
            'duplicate_similarity_threshold' => 0.5,
            'category_matching_mode' => 'child_preferred',
        ];
    }
};
