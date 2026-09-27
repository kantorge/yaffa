<?php

use App\Models\Category;
use App\Models\CategoryLearning;
use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);

    [$this->category, $otherCategory] = Category::where('user_id', $this->user->id)->whereNotNull('parent_id')->take(2)->get();
    $learning = fn (string $description, Category $category) => CategoryLearning::factory()->create([
        'user_id' => $this->user->id,
        'item_description' => $description,
        'category_id' => $category->id,
        'active' => true,
    ]);
    $this->source = $learning('learning merge source', $this->category);
    $this->target = $learning('learning merge target', $this->category);
    $learning('learning other category', $otherCategory);
});

// Not window.table: that's the browser's named access to <table id="table">, defined before the data loads
const LEARNING_TABLE_READY = 'window.categoryLearnings !== undefined';
const LEARNING_ROWS_SHOWN = "window.table.rows({ search: 'applied' }).count()";

it('narrows the table by the category filter and restores it when cleared', function () {
    $page = visit(route('category-learning.index'));
    $this->waitUntil($page, LEARNING_TABLE_READY);
    $allRows = $page->script('() => ' . LEARNING_ROWS_SHOWN);

    $this->chooseTomSelectOption($page, 'table_filter_category', $this->category->name, $this->category->full_name);
    $this->waitUntil($page, LEARNING_ROWS_SHOWN . ' === 2');

    $page->assertScript(LEARNING_ROWS_SHOWN, 2);

    $this->clearTomSelect($page, 'table_filter_category');
    $this->waitUntil($page, LEARNING_ROWS_SHOWN . " === {$allRows}");
    $page->assertNoJavaScriptErrors();
});

it('picks entries in the merge modal: typing works, the dropdown is not clipped, Esc closes only the dropdown', function () {
    $page = visit(route('category-learning.index'));
    $this->waitUntil($page, LEARNING_TABLE_READY);

    $page->click('#button-merge-learning');
    $this->waitUntil($page, "document.querySelector('#mergeCategoryLearningModal').classList.contains('show')");

    $this->searchTomSelect($page, 'merge_source_learning', 'learning merge');

    // The last option is what's actually rendered at its own position (nothing clips or covers it)
    $page->assertScript("(() => {
        const options = document.querySelectorAll('#merge_source_learning-ts-dropdown [data-selectable]');
        const last = options[options.length - 1];
        const r = last.getBoundingClientRect();
        return options.length === 2 && last.contains(document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2));
    })()", true);

    $page->keys('#merge_source_learning + .ts-wrapper .dropdown-input', 'Escape');
    $this->waitUntil($page, "!document.querySelector('#merge_source_learning').tomselect.isOpen");
    $page->assertScript("document.querySelector('#mergeCategoryLearningModal').classList.contains('show')", true);

    $label = fn (CategoryLearning $learning) => "{$learning->item_description} ({$this->category->full_name})";
    $this->chooseTomSelectOption($page, 'merge_source_learning', 'merge source', $label($this->source));
    $this->chooseTomSelectOption($page, 'merge_target_learning', 'merge target', $label($this->target));

    $page->click('#button-submit-merge-learning');
    $this->waitUntil($page, "!window.categoryLearnings.some((learning) => learning.id === {$this->source->id})");
    $page->assertNoJavaScriptErrors();

    expect(CategoryLearning::find($this->source->id))->toBeNull();
});

it('creates an entry with the category picked in the new-entry modal', function () {
    $page = visit(route('category-learning.index'));
    $this->waitUntil($page, LEARNING_TABLE_READY);

    $page->click('#button-new-learning');
    $this->waitUntil($page, "document.querySelector('#newCategoryLearningModal').classList.contains('show')");

    $page->type('#newCategoryLearningModal-item_description', 'learning from the modal');
    $this->chooseTomSelectOption($page, 'newCategoryLearningModal-category_id', $this->category->name, $this->category->full_name);
    $page->click('#newCategoryLearningModal button[type=submit]');

    $this->waitUntil($page, "window.categoryLearnings.some((learning) => learning.item_description === 'learning from the modal')");
    $page->assertNoJavaScriptErrors();

    expect(CategoryLearning::where('item_description', 'learning from the modal')->value('category_id'))->toBe($this->category->id);
});
