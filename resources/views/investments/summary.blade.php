@extends('template.page')

@section('title', 'Investment summary')

@section('content_header')
    <h1>Investment summary</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">Summary of investments</h3>
            <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <table class="table table-bordered table-hover dataTable" role="grid" id="table"></table>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->

@stop
