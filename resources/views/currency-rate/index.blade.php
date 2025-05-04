@extends('template.layouts.page')

@section('title', __('Currency rates'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Currency rates') . ' - ' . $from->iso_code . ' > ' . $to->iso_code }}
@stop

@section('content')
<div class="row">
    <div class="col-12 col-lg-3">
        <div id="app">
            <card-overview
                :from="{{ json_encode($from) }}"
                :to="{{ json_encode($to) }}"
                :currency-rates="{{ json_encode($currencyRates) }}"
            ></card-overview>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div
                        class="card-title collapse-control"
                        data-coreui-toggle="collapse"
                        data-coreui-target="#cardActions"
                >
                    <i class="fa fa-angle-down"></i>
                    {{ __('Actions') }}
                </div>
            </div>
            <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardActions">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a
                            class=""
                            href="{{ route('currency-rate.retrieveMissing', ['currency' =>  $from->id ]) }}"
                            title="{{ __('Load new currency rates') }}"
                    >
                        {{ __('Load new currency rates') }}
                    </a>
                    <a
                            class="btn btn-xs btn-success"
                            href="{{ route('currency-rate.retrieveMissing', ['currency' =>  $from->id ]) }}"
                            title="{{ __('Load new currency rates') }}"
                    >
                        <span class="fa fa-cloud-download"></span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div
                        class="card-title collapse-control"
                        data-coreui-toggle="collapse"
                        data-coreui-target="#cardFilters"
                >
                    <i class="fa fa-angle-down"></i>
                    {{ __('Filters') }}
                </div>
            </div>
            <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardFilters">
                @include('template.components.tablefilter-sidebar-search')

                <li class="list-group-item" id="dateRangePicker">

                    <div class="row">
                        <div class="col-6">
                            <label for="date_from" class="form-label">{{ __('Date from') }}</label>
                            <input type="text" class="form-control" name="date_from" id="date_from"
                                   placeholder="{{ __('Select date') }}" autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="date_to" class="form-label">{{ __('Date to') }}</label>
                            <input type="text" class="form-control" name="date_to" id="date_to"
                                   placeholder="{{ __('Select date') }}" autocomplete="off">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <select id="dateRangePickerPresets" class="form-select">
                                <option value="placeholder">{{ __('Select preset') }}</option>
                                <option value="thisMonth">{{ __('This month') }}</option>
                                <option value="thisQuarter">{{ __('This quarter') }}</option>
                                <option value="thisYear">{{ __('This year') }}</option>
                                <option value="thisMonthToDate">{{ __('This month to date') }}</option>
                                <option value="thisQuarterToDate">{{ __('This quarter to date') }}</option>
                                <option value="thisYearToDate">{{ __('This year to date') }}</option>
                                <option value="previousMonth">{{ __('Previous month') }}</option>
                                <option value="previousMonthToDate">{{ __('Previous month to date') }}</option>
                            </select>
                        </div>
                    </div>

                </li>
            </ul>
        </div>
    </div>
    <div class="col-12 col-lg-3">
        <div class="card">
            <div class="card-header">
                <div class="card-title">{{ __('Currency rate values') }}</div>
            </div>
            <div class="card-body no-datatable-search">
                <table class="table table-bordered table-hover" role="grid" id="table"></table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div id="chartdiv" style="width: 100%; height: 500px"></div>
            </div>
        </div>
    </div>
</div>

@include('template.components.model-delete-form')
@stop
