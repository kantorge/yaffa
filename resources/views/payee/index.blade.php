@extends('template.layouts.page')

@section('title_postfix',  __('Payees'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Payees'))

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            @include('template.components.tablefilter-active')
            <div>
                <a href="{{ route('account-entity.create', ['type' => 'payee']) }}" class="btn btn-success" title="{{ __('New payee') }}"><i class="fa fa-plus"></i></a>
                <a href="{{ route('payees.merge.form') }}" class="btn btn-primary" title="{{ __('Merge payees') }}"><i class="fa fa-random"></i></a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-striped table-bordered table-hover" role="grid" id="table"></table>
        </div>
    </div>

@include('template.components.model-delete-form')

@stop
