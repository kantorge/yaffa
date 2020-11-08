@extends('adminlte::page')

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
            <div class="card">
                <div class="card-header">
                    <div class="card-tools">
                        <a href="/currencies/create" class="btn btn-success" title="New currency"><i class="fa fa-plus"></i></a>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table class="table table-bordered table-hover dataTable" role="grid" id="table"></table>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div id="chartdiv" style="width: 100%; height: 500px"></div>
                </div>
            </div>
        </div>
    </div>
@stop