@extends('template.layouts.page')

@section('title_postfix',  __('Investment prices'))

@section('content_header')
    {{ __('Investment prices') }} - {{$investment->name}}
@stop

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span class="card-title">
                        {{ __('Investment price data') }}
                    </span>
                    <div>
                        <a href="{{ route('investment-price.create', ['investment' =>  $investment->id ]) }}" class="btn btn-sm btn-success"><i class="fa fa-plus"></i></a>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover" role="grid" id="table"></table>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div id="chartdiv" style="width: 100%; height: 500px"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('template.components.model-delete-form')
@stop
