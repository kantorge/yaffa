@extends('template.page')

@section('title', 'Scheduled transactions')

@section('content_header')
    <h1>Details of scheduled and budgeted transactions</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">
                Scheduled and budgeted transactions
            </h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <table class="table table-bordered table-hover no-footer" id="table"></table>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->

@stop
