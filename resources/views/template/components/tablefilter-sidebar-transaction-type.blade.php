<li class="list-group-item d-flex justify-content-between align-items-center">
    {{ __('Transaction type') }}
    <div
            aria-label="Toggle button group for transaction type"
            class="btn-group"
            dusk="button-group-table-filter-category-level"
            role="group"
    >
        <input type="radio" class="btn-check" name="table_filter_transaction_type" id="table_filter_transaction_type_standard" value="{{__('standard')}}">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_transaction_type_standard" title="{{ __('Standard') }}">
            <span class="fa fa-fw fa-shopping-cart"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_transaction_type" id="table_filter_category_level_any" value="" checked>
        <label class="btn btn-outline-primary btn-xs" for="table_filter_category_level_any" title="{{ __('Any') }}">
            <span class="fa fa-fw fa-circle"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_transaction_type" id="table_filter_transaction_type_investment" value="{{__('investment')}}">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_transaction_type_investment" title="{{ __('Investment') }}">
            <span class="fa fa-fw fa-line-chart"></span>
        </label>
    </div>
</li>
