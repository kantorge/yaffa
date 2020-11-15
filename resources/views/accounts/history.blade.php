@extends('adminlte::page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('title', 'Account  history')

@section('content_header')
    <h1>Account history</h1>
@stop

@section('content')

    <div class="card">
        <div class="card-header">
            Transaction history
            <div class="card-tools">
                <a href="/transactions/create" class="btn btn-success" title="New transaction"><i class="fa fa-plus"></i></a>
            </div>
            <!-- /.card-tools -->
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <table class="table table-bordered table-hover dataTable no-footer" role="grid" id="historyTable"></table>
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
            <table class="table table-bordered table-hover dataTable no-footer" role="grid" id="scheduleTable"></table>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

@stop