<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

class FixCategoryParentLoops extends Command
{
    protected $signature = 'categories:fix-parent-loops {--dry-run : Show affected categories without updating}';

    protected $description = 'Fix category rows where id equals parent_id by setting parent_id to null';

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
            $affected->map(fn (Category $category) => [
                $category->id,
                $category->user_id,
                $category->name,
                $category->parent_id,
            ])->all()
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
