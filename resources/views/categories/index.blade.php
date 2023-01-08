@extends('template.layouts.page')

@section('title_postfix',  __('Categories'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Categories'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        @include('template.components.tablefilter-active')
        <div>
            <a href="/categories/create" class="btn btn-success" title="{{ __('New category') }}"><i class="fa fa-plus"></i></a>
            <a href="{{ route('categories.merge.form') }}" class="btn btn-primary" title="{{ __('Merge categories') }}"><i class="fa fa-random"></i></a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-striped table-bordered table-hover" role="grid" id="table"></table>
    </div>
</div>

@include('template.components.model-delete-form')
@stop
