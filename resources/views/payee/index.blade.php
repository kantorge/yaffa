@extends('template.layouts.page')

@section('title', 'Payees')

@section('content_header')
    <h1>Payees</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">List of payees</h3>
            <div class="pull-right box-tools">
                <a href="{{ route('account-entity.create', ['type' => 'payee']) }}" class="btn btn-success" title="New payee"><i class="fa fa-plus"></i></a>
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