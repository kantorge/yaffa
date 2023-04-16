<li class="list-group-item d-flex justify-content-between align-items-center">
    {{ __('Active') }}
    <div
            aria-label="Toggle button group for active"
            class="btn-group"
            dusk="button-group-table-filter-active"
            role="group"
    >
        <input type="radio" class="btn-check" name="table_filter_active" id="table_filter_active_yes" value="{{ __('Yes') }}">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_active_yes" title="{{ __('Yes') }}">
            <span class="fa fa-fw fa-check"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_active" id="table_filter_active_any" value="" checked>
        <label class="btn btn-outline-primary btn-xs" for="table_filter_active_any" title="{{ __('Any') }}">
            <span class="fa fa-fw fa-circle"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_active" id="table_filter_active_no" value="{{ __('No') }}">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_active_no" title="{{ __('No') }}">
            <span class="fa fa-fw fa-close"></span>
        </label>
    </div>
</li>
