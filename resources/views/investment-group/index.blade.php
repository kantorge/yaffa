@extends('template.layouts.page')

@section('title_postfix',  __('Investment groups'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Investment groups'))

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <div></div>
            <div>
                <a href="{{ route('investment-group.create') }}" class="btn btn-success" title="{{ __('New investment group') }}"><i class="fa fa-plus"></i></a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-striped table-bordered table-hover" role="grid" id="table"></table>
        </div>
    </div>

@include('template.components.model-delete-form')
@stop
