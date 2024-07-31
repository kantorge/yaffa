@extends('template.layouts.page')

@section('title_postfix',  __('Bank connections'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Bank connections'))

@section('content')
    <div class="row">
        <div class="col-12 col-lg-3">
            <div class="card mb-3">
                <div class="card-header">
                    <div
                            class="card-title collapse-control"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardActions"
                    >
                        <i class="fa fa-angle-down"></i>
                        {{ __('Actions') }}
                    </div>
                </div>
                <ul
                        class="list-group list-group-flush collapse show"
                        aria-expanded="true"
                        id="cardActions"
                >
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('New bank connection') }}
                        <a class="btn btn-success btn-sm"
                           dusk="button-new-bank-connection"
                           href="{{ route('gocardless.create') }}" title="{{ __('New bank connection') }}"
                        >
                            <i class="fa fa-fw fa-plus" title="{{ __('New bank connection') }}"></i>
                        </a>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Manage account links') }}
                        <a
                                class="btn btn-primary btn-sm"
                                href="{{ route('gocardless.linkAccounts')}}"
                                title="{{ __('Manage account links') }}"
                        >
                            <i class="fa fa-fw fa-link"></i>
                        </a>
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <div
                            class="card-title collapse-control"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardFilters"
                    >
                        <i class="fa fa-angle-down"></i>
                        {{ __('Filters') }}
                    </div>
                </div>
                <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardFilters">
                    @include('template.components.tablefilter-sidebar-search')
                    <li class="list-group-item">
                        <div id="requisition-status-tree-container"></div>
                    </li>
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-9">
            <div class="card mb-3">
                <div class="card-body no-datatable-search">
                    <table
                            class="table table-striped table-bordered table-hover"
                            dusk="table-requisitions"
                            id="table"
                            role="grid"
                    ></table>
                </div>
            </div>
        </div>
    </div>

    @include('template.components.model-delete-form')
@stop
