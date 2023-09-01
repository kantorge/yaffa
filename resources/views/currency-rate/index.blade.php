@extends('template.layouts.page')

@section('title', __('Currency rates'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Currency rates'))

@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <div class="card-title">{{ __('Currency rate values') }}</div>
                <div>
                    <a href="{{ route('currency-rate.retreiveMissing', ['currency' =>  $from->id ]) }}" class="btn btn-success" title="{{ __('Load new currency rates') }}">
                        <span class="fa fa-cloud-download"></span>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-hover" role="grid" id="table"></table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div id="chartdiv" style="width: 100%; height: 500px"></div>
            </div>
        </div>
    </div>
</div>

@include('template.components.model-delete-form')
@stop
