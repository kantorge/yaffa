@extends('template.page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('title', 'Currency rates')

@section('content_header')
    <h1>Currency rates</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">Currency rate values</h3>
                    <div class="pull-right box-tools">
                        <a href="/currencies/create" class="btn btn-success" title="New currency rate"><i class="fa fa-plus"></i></a>
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
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-body">
                    <div id="chartdiv" style="width: 100%; height: 500px"></div>
                </div>
            </div>
        </div>
    </div>
@stop