@extends('template.layouts.page')

@section('title_postfix', __('Transactions by criteria'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Transactions by criteria'))

@section('content')
    <div class="row">
        <div class="col-sm-3">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Date') }}
                    </div>
                    <div>
                        <button class="btn btn-sm btn-primary" id="clear_dates">{{ __('Clear selection') }}</button>
                    </div>
                </div>
                <div class="card-body" id="dateRangePicker">
                    <div class="row">
                        <div class="col-6">
                            <label for="date_from" class="form-label">{{ __('Date from') }}</label>
                            <input
                                    class="form-control"
                                    name="date_from"
                                    id="date_from"
                                    placeholder="{{ __('Select date') }}"
                                    autocomplete="off"
                                    type="text"
                            >
                        </div>
                        <div class="col-6">
                            <label for="date_to" class="form-label">{{ __('Date to') }}</label>
                            <input
                                    class="form-control"
                                    name="date_to"
                                    id="date_to"
                                    placeholder="{{ __('Select date') }}"
                                    autocomplete="off"
                                    type="text"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Category') }}
                    </div>
                    <div>
                        <button class="btn btn-sm btn-primary clear-select" data-target="select_category">
                            {{ __('Clear selection') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <select id="select_category" class="form-select"></select>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Payee') }}
                    </div>
                    <div>
                        <button class="btn btn-sm btn-primary clear-select" data-target="select_payee">
                            {{ __('Clear selection') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <select id="select_payee" class="form-select"></select>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Account') }}
                    </div>
                    <div>
                        <button class="btn btn-sm btn-primary clear-select" data-target="select_account">
                            {{ __('Clear selection') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <select id="select_account" class="form-select"></select>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Tag') }}
                    </div>
                    <div>
                        <button class="btn btn-sm btn-primary clear-select" data-target="select_tag">
                            {{ __('Clear selection') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <select id="select_tag" class="form-select"></select>
                </div>
            </div>
        </div>
        <div class="col-sm-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Transactions') }}
                    </div>
                    <div>
                        <button name="reload" type="button" id="reload" class="btn btn-sm btn-primary">
                            {{ __('Update') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover no-footer" id="dataTable"></table>
                </div>
            </div>
        </div>
    </div>

    <div id="app">
        <transaction-show-modal></transaction-show-modal>
    </div>
@stop
