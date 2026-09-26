<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:category:fix-self-references {--dry-run : Show affected categories without updating}')]
#[Description('Fix self-referencing categories (id = parent_id) by setting parent_id to null')]
class FixCategorySelfReferences extends Command
{
    public function handle(): int
    {
        $affected = Category::query()
            ->whereColumn('id', 'parent_id')
            ->get(['id', 'user_id', 'name', 'parent_id']);

        if ($affected->isEmpty()) {
            $this->info('No self-referencing categories found.');

            return Command::SUCCESS;
        }

        $this->warn(sprintf('Found %d self-referencing categories.', $affected->count()));
        $this->table(
            ['id', 'user_id', 'name', 'parent_id'],
            $affected->toArray()
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run mode: no changes applied.');

            return Command::SUCCESS;
        }

        $updatedRows = Category::query()
            ->whereColumn('id', 'parent_id')
            ->update(['parent_id' => null]);

        $this->info(sprintf('Updated %d categories.', $updatedRows));

        return Command::SUCCESS;
    }
}
