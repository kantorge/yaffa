<li class="list-group-item d-flex justify-content-between align-items-center">
    {{ __('Parent or children') }}
    <div
            aria-label="Toggle button group for category level"
            class="btn-group"
            data-test="button-group-table-filter-category-level"
            role="group"
    >
        <input type="radio" class="btn-check" name="table_filter_category_level" id="table_filter_category_level_parent" value="parents">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_category_level_parent" title="{{ __('Parent') }}">
            <span class="fa fa-fw fa-user"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_category_level" id="table_filter_category_level_any" value="" checked>
        <label class="btn btn-outline-primary btn-xs" for="table_filter_category_level_any" title="{{ __('Any') }}">
            <span class="fa fa-fw fa-circle"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_category_level" id="table_filter_category_level_child" value="children">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_category_level_child" title="{{ __('Child') }}">
            <span class="fa fa-fw fa-children"></span>
        </label>
    </div>
</li>
