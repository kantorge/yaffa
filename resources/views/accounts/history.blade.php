@extends('template.page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('title', 'Account  history')

@section('content_header')
    <h1>Account history - {{ $account->config->name }}</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">
                Transaction history
            </h3>
            <div class="pull-right box-tools">
                <a
                    class="btn {{($withForecast ? 'btn-primary' : 'btn-info') }}"
                    href="{{ route('accounts.history', ['account' => $account->id, 'withForecast' => ($withForecast ? '' : 'withForecast')]) }}"
                    title="{{($withForecast ? 'Without forecast' : 'With forecast') }}">
                    <i class="fa fa-calendar"></i>
                </a>
                <a href="/transactions/create/standard" class="btn btn-success" title="New transaction"><i class="fa fa-plus"></i></a>
                <a href="/transactions/create/investment" class="btn btn-success" title="New investment transaction"><i class="fa fa-line-chart"></i></a>
            </div>
            <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <table class="table table-bordered table-hover no-footer" id="historyTable"></table>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->

    <div class="box">
        <div class="box-header">
            Scheduled transactions
            <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <table class="table table-bordered table-hover dataTable no-footer" id="scheduleTable"></table>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->

@stop