@extends('template.layouts.page')

@section('title', __('Payees'))

@section('content_header', __('Payees'))

@section('content')

    <div class="box">
        <div class="box-header">
            <div class="row">
                <div class="col-lg-12">
                    @include('template.components.tablefilter-active')
                </div>
            </div>
            <div class="pull-right box-tools">
                <a href="{{ route('account-entity.create', ['type' => 'payee']) }}" class="btn btn-success" title="{{ __('New payee') }}"><i class="fa fa-plus"></i></a>
                <a href="{{ route('payees.merge.form') }}" class="btn btn-primary" title="{{ __('Merge payees') }}"><i class="fa fa-random"></i></a>
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
