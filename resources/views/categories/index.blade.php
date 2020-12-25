@extends('template.page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('title', 'Categories')

@section('content_header')
    <h1>Categories</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title"></h3>
            <div class="pull-right box-tools">
                <a href="/categories/create" class="btn btn-success" title="New category"><i class="fa fa-plus"></i></a>
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