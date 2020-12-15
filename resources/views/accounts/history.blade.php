@extends('adminlte::page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('title', 'Account  history')

@section('content_header')
    <h1>Account history - {{ $account->config->name }}</h1>
@stop

@section('content')

    <div class="card">
        <div class="card-header">
            <a data-toggle="collapse" href="#collapseHistory" aria-expanded="true">
                Transaction history
            </a>
            <div class="card-tools">
                <a
                    class="btn {{($withForecast ? 'btn-primary' : 'btn-secondary') }}"
                    href="{{ route('accounts.history', ['account' => $account->id, 'withForecast' => ($withForecast ? '' : 'withForecast')]) }}"
                    title="{{($withForecast ? 'Without forecast' : 'With forecast') }}">
                    <i class="fa fa-calendar"></i>
                </a>
                <a href="/transactions/create/standard" class="btn btn-success" title="New transaction"><i class="fa fa-plus"></i></a>
                <a href="/transactions/create/investment" class="btn btn-success" title="New investment transaction"><i class="fa fa-chart-line"></i></a>
            </div>
            <!-- /.card-tools -->
        </div>
        <!-- /.card-header -->
        <div id="collapseHistory" class="panel-collapse collapse show">
            <div class="card-body">
                <table class="table table-bordered table-hover dataTable no-footer" id="historyTable"></table>
            </div>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

    <div class="card">
        <div class="card-header">
            Scheduled transactions
            <!-- /.card-tools -->
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <table class="table table-bordered table-hover dataTable no-footer" id="scheduleTable"></table>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

@stop