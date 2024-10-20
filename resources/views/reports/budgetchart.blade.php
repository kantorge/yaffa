@extends('template.layouts.page')

@section('title_postfix', __('Budget chart'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Budget chart'))

@section('content')
<div class="row">
    <div class="col-lg-3">
        <div class="row">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <div
                                class="card-title collapse-control"
                                data-coreui-toggle="collapse"
                                data-coreui-target="#cardCategories"
                        >
                            <i class="fa fa-angle-down"></i>
                            {{ __('Select categories to display') }}
                        </div>
                    </div>
                    <div class="card-body collapse show" aria-expanded="true" id="cardCategories">
                        <div id="categoryTree"></div>
                        <div class="text-end">
                            <button name="reload" type="button" id="clear" class="btn btn-default btn-sm">{{ __('Clear selection') }}</button>
                            <button name="reload" type="button" id="all" class="btn btn-default btn-sm">{{ __('Select all') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <div
                                class="card-title collapse-control"
                                data-coreui-toggle="collapse"
                                data-coreui-target="#cardAccounts"
                        >
                            <i class="fa fa-angle-down"></i>
                            {{ __('Accounts') }}
                        </div>
                    </div>
                    <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardAccounts">
                        @include('template.components.tablefilter-sidebar-budget-account')
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <select class="form-select" id="accountList"></select>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <div class="text-start">
                    <button name="reload" type="button" id="reload" class="btn btn-primary">{{ __('Load data') }}</button>
                </div>
                <div class="text-end">
                    <div
                            aria-label="Toggle button group for time interval"
                            class="btn-group"
                            role="group"
                    >
                        <input type="radio" class="btn-check" name="chart_time_interval" id="chart_time_interval_month" value="month" checked>
                        <label class="btn btn-outline-primary btn-sm" for="chart_time_interval_month" title="{{ __('Month') }}">
                            <span class="fa fa-fw fa-solid fa-calendar-day"></span>
                        </label>

                        <input type="radio" class="btn-check" name="chart_time_interval" id="chart_time_interval_quarter" value="quarter">
                        <label class="btn btn-outline-primary btn-sm" for="chart_time_interval_quarter" title="{{ __('Quarter') }}">
                            <span class="fa fa-fw fa-regular fa-calendar-days"></span>
                        </label>

                        <input type="radio" class="btn-check" name="chart_time_interval" id="chart_time_interval_year" value="year">
                        <label class="btn btn-outline-primary btn-sm" for="chart_time_interval_year" title="{{ __('Year') }}">
                            <span class="fa fa-fw fa-regular fa-calendar"></span>
                        </label>
                    </div>

                    <button type="button" class="btn btn-sm btn-primary" title="{{ __('Zoom in') }}" id="btnZoomIn">
                        <i class="fa fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="chartdiv" style="width:100%;height:500px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title">
                    {{ __('Scheduled and budgeted transactions for selected categories') }}
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-hover no-footer" id="table"></table>
            </div>
        </div>
    </div>
</div>

@include('template.components.model-delete-form')
@include('template.components.transaction-skip-form')

@stop
