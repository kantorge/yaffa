@extends('template.layouts.page')

@section('title', __('Transactions by criteria'))

@section('content_header', __('Transactions by criteria'))

@section('content')

    <div class="row">
        <div class="col-sm-9">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">
                        {{ __('Transactions') }}
                    </h3>
                    <div class="box-tools pull-right">
                        <button name="reload" type="button" id="reload" class="btn btn-primary pull-right">{{ __('Update') }}</button>
                    </div>
                </div>
                <div class="box-body">
                    <table class="table table-bordered table-hover dataTable no-footer" id="dataTable" style="width: 100%;"></table>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Date</h3>
                    <div class="box-tools pull-right">
                        <button class="btn btn-xs btn-primary" id="clear_dates">{{ __('Clear selection') }}</button>
                    </div>
                </div>
                <div class="box-body form-horizontal" id="dateRangePicker">
                    <div class="form-group">
                        <label for="date_from" class="col-sm-4 control-label">{{ __('Date from') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="date_from" id="date_from" placeholder="Select date" autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_to" class="col-sm-4 control-label">{{ __('Date to') }}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="date_to" id="date_to" placeholder="Select date" autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Category') }}</h3>
                    <div class="box-tools pull-right">
                        <button class="btn btn-xs btn-primary clear-select" data-target="select_category">{{ __('Clear selection') }}</button>
                    </div>
                </div>
                <div class="box-body">
                    <select id="select_category" class="form-control"></select>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Payee') }}</h3>
                    <div class="box-tools pull-right">
                    <button class="btn btn-xs btn-primary clear-select" data-target="select_payee">{{ __('Clear selection') }}</button>
                    </div>
                </div>
                <div class="box-body">
                    <select id="select_payee" class="form-control"></select>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Account') }}</h3>
                    <div class="box-tools pull-right">
                        <button class="btn btn-xs btn-primary clear-select" data-target="select_account">{{ __('Clear selection') }}</button>
                    </div>
                </div>
                <div class="box-body">
                    <select id="select_account" class="form-control"></select>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Tag') }}</h3>
                    <div class="box-tools pull-right">
                        <button class="btn btn-xs btn-primary clear-select" data-target="select_tag">{{ __('Clear selection') }}</button>
                    </div>
                </div>
                <div class="box-body">
                    <select id="select_tag" class="form-control"></select>
                </div>
            </div>
        </div>
    </div>

    <div id="app">
        <transaction-show-modal></transaction-show-modal>
    </div>

    @include('template.components.model-delete-form')

@stop
