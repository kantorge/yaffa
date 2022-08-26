@extends('template.layouts.page')

@section('title', 'Account groups')

@section('content_header')
    <h1>Account groups</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <div class="pull-right box-tools">
                <a href="{{ route('account-group.create') }}" class="btn btn-success" title="New account group">
                    <i class="fa fa fa-plus"></i>
                </a>
            </div>
            <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <table class="table table-striped table-bordered table-hover" role="grid" id="table"></table>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->

    @include('template.components.model-delete-form')

@stop
