@extends('template.layouts.page')

@section('title', __('Currencies'))

@section('content_header', __('Currencies'))

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">{{ __('List of currencies') }}</h3>
            <div class="pull-right box-tools">
                <a href="/currencies/create" class="btn btn-success" title="{{ __('New currency') }}"><i class="fa fa-plus"></i></a>
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

    @include('template.components.model-delete-form')

@stop
