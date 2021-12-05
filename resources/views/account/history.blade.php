@extends('template.layouts.page')

@section('title', 'Account  history')

@section('content_header')
    <h1>Account history - {{ $account->name }}</h1>
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
                    href="{{ route('account.history', ['account' => $account->id, 'withForecast' => ($withForecast ? '' : 'withForecast')]) }}"
                    title="{{($withForecast ? 'Without forecast' : 'With forecast') }}">
                    <i class="fa fa-calendar"></i>
                </a>
                <a href="{{ route('transactions.createStandard', ['account_from' => $account->id ]) }}" class="btn btn-success" title="New transaction"><i class="fa fa-plus"></i></a>
                <a href="{{ route('transactions.createInvestment', ['account' => $account->id ]) }}" class="btn btn-success" title="New investment transaction"><i class="fa fa-line-chart"></i></a>
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

    @include('template.components.model-delete-form')

    @include('template.components.transaction-skip-form')

@stop
