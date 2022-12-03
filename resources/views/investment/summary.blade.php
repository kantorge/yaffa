@extends('template.layouts.page')

@section('title_postfix', __('Investment summary'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Investment summary'))

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            @include('template.components.tablefilter-active')
        </div>
        <div class="card-body">
            <table class="table table-striped table-bordered table-hover" role="grid" id="investmentSummary"></table>
        </div>
    </div>
@stop
