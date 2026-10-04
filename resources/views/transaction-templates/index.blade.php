@extends('template.layouts.page')

@section('title_postfix',  __('Transaction templates'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Transaction templates'))

@section('content')
    <div class="row">
        <div class="col-12 col-lg-3">
            <div class="card mb-3">
                <div class="card-header">
                    <div class="card-title">{{ __('Actions') }}</div>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('New standard template') }}
                        <a class="btn btn-success btn-sm"
                           href="{{ route('transaction-templates.create', 'standard') }}"
                           title="{{ __('New standard template') }}"
                        >
                            <i class="fa fa-plus"></i>
                        </a>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('New investment template') }}
                        <a class="btn btn-success btn-sm"
                           href="{{ route('transaction-templates.create', 'investment') }}"
                           title="{{ __('New investment template') }}"
                        >
                            <i class="fa fa-plus"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-9">
            <div class="card mb-3">
                <div class="card-body no-datatable-search">
                    <table
                            class="table table-striped table-bordered table-hover"
                            dusk="table-transaction-templates"
                            id="table"
                            role="grid"
                    ></table>
                </div>
            </div>
        </div>
    </div>
@stop
