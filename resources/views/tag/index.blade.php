@extends('template.layouts.page')

@section('title', __('Tags'))

@section('content_header')
    {{ __('Tags') }}
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <div class="pull-right box-tools">
                <a href="{{ route('tag.create') }}" class="btn btn-success" title="{{ __('New tag') }}"><i class="fa fa-plus"></i></a>
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
