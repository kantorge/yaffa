@extends('template.page')

@section('title', 'Investment groups')

@section('content_header')
    <h1>Investment groups</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">List of investment groups</h3>
            <div class="pull-right box-tools">
                <a href="{{ route('investment-group.create') }}" class="btn btn-success" title="New investment group"><i class="fa fa-plus"></i></a>
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