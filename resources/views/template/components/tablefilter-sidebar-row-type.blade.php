<li class="list-group-item d-flex justify-content-between align-items-center" id="filter-row-type">
    {{ __('Type') }}
    <div
            aria-label="{{ __('Toggle button group for row type filtering') }}"
            class="btn-group"
            dusk="button-group-table-filter-row-type"
            role="group"
    >
        <input type="radio" class="btn-check" name="table_filter_row_type" id="table_filter_row_type_schedule" value="{{ __('Schedule') }}">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_row_type_schedule" title="{{ __('Schedule') }}">
            <span class="fa fa-fw fa-repeat"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_row_type" id="table_filter_row_type_any" value="" checked>
        <label class="btn btn-outline-primary btn-xs" for="table_filter_row_type_any" title="{{ __('Any') }}">
            <span class="fa fa-fw fa-circle"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_row_type" id="table_filter_row_type_budget" value="{{ __('Budget') }}">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_row_type_budget" title="{{ __('Budget') }}">
            <span class="fa fa-fw fa-piggy-bank"></span>
        </label>
    </div>
</li>
