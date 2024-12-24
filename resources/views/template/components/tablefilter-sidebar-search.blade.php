<li class="list-group-item d-flex justify-content-between align-items-center">
    <label class="col-6" for="table_filter_search_text">
        {{ __('Search') }}
    </label>
    <div class="input-group input-group-sm">
        <input
                autocomplete="off"
                class="form-control form-control-sm"
                dusk="input-table-filter-search"
                id="table_filter_search_text"
                type="text"
        >
        <button
                class="btn btn-outline-secondary"
                type="button"
                id="table_filter_search_text_clear"
                title="{{ __('Clear search') }}"
        >
            <i class="fa fa-times"></i>
        </button>
    </div>
</li>
