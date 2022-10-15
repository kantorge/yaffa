@extends('template.layouts.page')

@section('title', __('Investment prices'))

@section('content_header')
    {{ __('Investment prices') }} - {{$investment->name}}
@stop

@section('content')
    <div class="row">
        <div class="col-md-4">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">
                        {{ __('Investment price data') }}
                    </h3>
                    <div class="pull-right box-tools">
                        <a href="{{ route('investment-price.create', ['investment' =>  $investment->id ]) }}" class="btn btn-sm btn-success"><i class="fa fa-plus"></i></a>
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
        </div>
        <div class="col-md-8">
            <div class="box">
                <div class="box-body">
                    <div id="chartdiv" style="width: 100%; height: 500px"></div>
                </div>
            </div>
        </div>
    </div>

    @include('template.components.model-delete-form')

@stop
