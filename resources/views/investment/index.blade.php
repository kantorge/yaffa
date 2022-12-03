@extends('template.layouts.page')

@section('title_postfix', __('Investments'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Investments'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        @include('template.components.tablefilter-active')
        <div>
            <a href="{{ route('investment.create') }}" class="btn btn-success" title="{{ __('New investment') }}"><i class="fa fa-plus"></i></a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-striped table-bordered table-hover" role="grid" id="table"></table>
    </div>
</div>

@include('template.components.model-delete-form')
@stop
