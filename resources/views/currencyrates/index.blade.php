@extends('template.layouts.page')

@section('title', __('Currency rates'))

@section('content_header', __('Currency rates'))

@section('content')
    <div class="row">
        <div class="col-md-4">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">{{ __('Currency rate values') }}</h3>
                    <div class="pull-right box-tools">
                        <a href="{{ route('currencyrate.retreiveMissing', ['currency' =>  $from->id ]) }}" class="btn btn-success" title="{{ __('Load new currency rates') }}">
                            <span class="fa fa-cloud-download"></span>
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
