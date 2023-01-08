@extends('template.layouts.page')

@section('title_postfix',  __('Currencies'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Currencies'))

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <div class="card-title">{{ __('List of currencies') }}</div>
            <div class="btn-toolbar">
                <a href="/currencies/create" class="btn btn-sm btn-success" title="{{ __('New currency') }}"><i class="fa fa-plus"></i></a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-striped table-bordered table-hover" role="grid" id="table"></table>
        </div>
    </div>

@include('template.components.model-delete-form')
@stop
