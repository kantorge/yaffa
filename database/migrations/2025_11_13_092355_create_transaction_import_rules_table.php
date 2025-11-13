<?php

use App\Models\User;
use App\Models\AccountEntity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_import_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(AccountEntity::class, 'account_id')->nullable()->constrained('account_entities')->nullOnDelete();
            
            // Matching criteria
            $table->string('description_pattern'); // Exact match or pattern
            $table->boolean('use_regex')->default(false);
            
            // Rule action
            $table->enum('action', ['convert_to_transfer', 'skip', 'modify'])->default('convert_to_transfer');
            
            // For convert_to_transfer action
            $table->foreignIdFor(AccountEntity::class, 'transfer_account_id')->nullable()->constrained('account_entities')->nullOnDelete();
            $table->integer('transaction_type_id')->default(3); // 3 = transfer
            
            // Priority for rule matching (lower number = higher priority)
            $table->integer('priority')->default(100);
            
            // Active flag
            $table->boolean('active')->default(true);
            
            $table->timestamps();
            
            // Index for faster lookups
            $table->index(['user_id', 'account_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_import_rules');
    }
};
