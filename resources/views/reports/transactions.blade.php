@extends('template.layouts.page')

@section('title', 'Transactions by criteria')

@section('content_header')
    <h1>Transactions by criteria</h1>
@stop

@section('content')

    <div class="row">
        <div class="col-sm-9">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">Transactions</h3>
                    <div class="box-tools pull-right">
                        <button name="reload" type="button" id="reload" class="btn btn-primary pull-right">Update</button>
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
                        <button class="btn btn-xs btn-primary" id="clear_dates">Clear selection</button>
                    </div>
                </div>
                <div class="box-body form-horizontal" id="dateRangePicker">
                    <div class="form-group">
                        <label for="date_from" class="col-sm-4 control-label">Date from</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="date_from" id="date_from" placeholder="Select date" autocomplete="off">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_to" class="col-sm-4 control-label">Date to</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="date_to" id="date_to" placeholder="Select date" autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Category</h3>
                    <div class="box-tools pull-right">
                        <button class="btn btn-xs btn-primary clear-select" data-target="select_category">Clear selection</button>
                    </div>
                </div>
                <div class="box-body">
                    <select id="select_category" class="form-control"></select>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Payee</h3>
                    <div class="box-tools pull-right">
                    <button class="btn btn-xs btn-primary clear-select" data-target="select_payee">Clear selection</button>
                    </div>
                </div>
                <div class="box-body">
                    <select id="select_payee" class="form-control"></select>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Account</h3>
                    <div class="box-tools pull-right">
                        <button class="btn btn-xs btn-primary clear-select" data-target="select_account">Clear selection</button>
                    </div>
                </div>
                <div class="box-body">
                    <select id="select_account" class="form-control"></select>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Tag</h3>
                    <div class="box-tools pull-right">
                        <button class="btn btn-xs btn-primary clear-select" data-target="select_tag">Clear selection</button>
                    </div>
                </div>
                <div class="box-body">
                    <select id="select_tag" class="form-control"></select>
                </div>
            </div>
        </div>
    </div>

    <div id="app">
        <transaction-show-modal
            element="#dataTable"
            selector=".data-quickview"
        ></transaction-show-modal>
    </div>

    @include('template.components.model-delete-form')

@stop
