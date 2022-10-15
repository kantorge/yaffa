@extends('template.layouts.page')

@section('title', __('Categories'))

@section('content_header', __('Categories'))

@section('content')

    <div class="box">
        <div class="box-header">
            <div class="row">
                <div class="col-lg-12">
                    @include('template.components.tablefilter-active')
                </div>
            </div>
            <div class="pull-right box-tools">
                <a href="/categories/create" class="btn btn-success" title="{{ __('New category') }}"><i class="fa fa-plus"></i></a>
                <a href="{{ route('categories.merge.form') }}" class="btn btn-primary" title="{{ __('Merge categories') }}"><i class="fa fa-random"></i></a>
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
