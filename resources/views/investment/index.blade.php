@extends('template.layouts.page')

@section('title', 'Investments')

@section('content_header')
    <h1>Investments</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <div class="row">
                <div class="col-lg-12">
                    @include('template.components.tablefilter-active')
                </div>
            </div>
            <div class="pull-right box-tools">
                <a href="{{ route('investment.create') }}" class="btn btn-success" title="New investment"><i class="fa fa-plus"></i></a>
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
