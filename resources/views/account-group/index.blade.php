@extends('template.layouts.page')

@section('title', 'Account groups')

@section('content_header')
    <h1>Account groups</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">List of account groups</h3>
            <div class="pull-right box-tools">
                <a href="{{ route('account-group.create') }}" class="btn btn-success" title="New account group">
                    <i class="fa fa fa-plus"></i>
                    Add new account group
                </a>
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
