@extends('template.layouts.page')

@section('title_postfix',  __('Tags'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Tags'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        @include('template.components.tablefilter-active')
        <div>
            <a href="{{ route('tag.create') }}" class="btn btn-success" title="{{ __('New tag') }}"><i class="fa fa-plus"></i></a>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-striped table-bordered table-hover" role="grid" id="table"></table>
    </div>
</div>

@include('template.components.model-delete-form')
@stop
