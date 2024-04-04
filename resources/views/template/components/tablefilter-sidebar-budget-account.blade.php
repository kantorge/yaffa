<li class="list-group-item d-flex justify-content-between align-items-center">
    {{ __('Account scope') }}
    <div
            aria-label="Toggle button group for account filtering"
            class="btn-group"
            dusk="button-group-table-filter-account-scope"
            role="group"
    >
        <input type="radio" class="btn-check" name="table_filter_account_scope" id="table_filter_account_scope_selected" value="selected">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_account_scope_selected" title="{{ __('Selected') }}">
            <span class="fa fa-fw fa-circle-check"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_account_scope" id="table_filter_account_scope_any" value="any" checked>
        <label class="btn btn-outline-primary btn-xs" for="table_filter_account_scope_any" title="{{ __('Any') }}">
            <span class="fa fa-fw fa-circle"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_account_scope" id="table_filter_account_scope_none" value="none">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_account_scope_none" title="{{ __('None') }}">
            <span class="fa fa-fw fa-regular fa-circle"></span>
        </label>
    </div>
</li>
