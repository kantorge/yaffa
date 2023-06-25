@props(['label' => '', 'property' => ''])

<li class="list-group-item d-flex justify-content-between align-items-center">
    {{ $label }}
    <div
            aria-label="Toggle button group for {{ $property }}"
            class="btn-group"
            dusk="button-group-table-filter-{{ $property }}"
            role="group"
    >
        <input type="radio" class="btn-check" name="table_filter_{{ $property }}" id="table_filter_{{ $property }}_yes" value="{{ __('Yes') }}">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_{{ $property }}_yes" title="{{ __('Yes') }}">
            <span class="fa fa-fw fa-check"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_{{ $property }}" id="table_filter_{{ $property }}_any" value="" checked>
        <label class="btn btn-outline-primary btn-xs" for="table_filter_{{ $property }}_any" title="{{ __('Any') }}">
            <span class="fa fa-fw fa-circle"></span>
        </label>

        <input type="radio" class="btn-check" name="table_filter_{{ $property }}" id="table_filter_{{ $property }}_no" value="{{ __('No') }}">
        <label class="btn btn-outline-primary btn-xs" for="table_filter_{{ $property }}_no" title="{{ __('No') }}">
            <span class="fa fa-fw fa-close"></span>
        </label>
    </div>
</li>
