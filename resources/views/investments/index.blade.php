@extends('template.page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('title', 'Investments')

@section('content_header')
    <h1>Investments</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">List of investment groups</h3>
            <div class="pull-right box-tools">
                <a href="/investments/create" class="btn btn-success" title="New investment"><i class="fa fa-plus"></i></a>
            </div>
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