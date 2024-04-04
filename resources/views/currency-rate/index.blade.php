@extends('template.layouts.page')

@section('title', __('Currency rates'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Currency rates') . ' - ' . $from->iso_code . ' > ' . $to->iso_code }}
@stop

@section('content')
<div class="row">
    <div class="col-12 col-lg-3">
        <div class="card mb-3">
            <div class="card-header">
                <div
                        class="card-title collapse-control"
                        data-coreui-toggle="collapse"
                        data-coreui-target="#cardOverview"
                >
                    <i class="fa fa-angle-down"></i>
                    {{ __('Overview') }}
                </div>
            </div>
            <div class="collapse card-body show" aria-expanded="true" id="cardOverview">
                <dl class="row mb-0">
                    <dt class="col-6">{{ __('From') }}</dt>
                    <dd class="col-6">{{ $from->name }}</dd>
                    <dt class="col-6">{{ __('To') }}</dt>
                    <dd class="col-6">{{ $to->name }}</dd>
                    <dt class="col-6">{{ __('Number of records') }}</dt>
                    <dd class="col-6">{{ $currencyRates->count() }}</dd>
                    <dt class="col-6">{{ __('First avaiable data') }}</dt>
                    @if ($currencyRates->count() > 0)
                        <dd class="col-6">
                            {{ $currencyRates->first('date')?->date->locale(auth()->user()->locale)->isoFormat('LL') }}
                        </dd>
                    @else
                        <dd class="col-6 text-italic text-muted">
                            {{ __('No data') }}
                        </dd>
                    @endif
                    <dt class="col-6">{{ __('Last available data') }}</dt>
                    @if ($currencyRates->count() > 0)
                        <dd class="col-6">
                            {{ $currencyRates->last('date')?->date->locale(auth()->user()->locale)->isoFormat('LL') }}
                        </dd>
                    @else
                        <dd class="col-6 text-italic text-muted">
                            {{ __('No data') }}
                        </dd>
                    @endif
                    <dt class="col-6">{{ __('Last known rate') }}</dt>
                    @if ($currencyRates->count() > 0)
                        <dd class="col-6">
                            {{ \Illuminate\Support\Number::currency(
                                    1,
                                    $from->iso_code,
                                    auth()->user()->locale
                               ) }}
                            =
                            {{ \Illuminate\Support\Number::currency(
                                    $currencyRates->last('date')?->rate ?? 0,
                                    $to->iso_code,
                                    auth()->user()->locale
                               ) }}
                        </dd>
                    @else
                        <dd class="col-6 text-italic text-muted">
                            {{ __('No data') }}
                        </dd>
                    @endif
                </dl>
            </div>
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
                            href="{{ route('currency-rate.retreiveMissing', ['currency' =>  $from->id ]) }}"
                            title="{{ __('Load new currency rates') }}"
                    >
                        {{ __('Load new currency rates') }}
                    </a>
                    <a
                            class="btn btn-xs btn-success"
                            href="{{ route('currency-rate.retreiveMissing', ['currency' =>  $from->id ]) }}"
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
