@extends('template.layouts.page')

@section('title_postfix',  __('Accounts'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Accounts'))

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
                <div class="card-body collapse show" aria-expanded="true" id="cardActions">
                    <ul class="nav flex-column">
                        <li class="nav-item d-flex justify-content-between align-items-center">
                            {{ __('New account') }}
                            <a class="btn btn-success"
                               dusk="button-new-account"
                               href="{{ route('account-entity.create', ['type' => 'account']) }}"
                               title="{{ __('New account') }}"
                            >
                                <i class="fa fa-plus"></i>
                            </a>
                        </li>
                    </ul>
                </div>
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
                    <x-tablefilter-sidebar-switch
                            label=" {{ __('Active') }}"
                            property="active"
                    />
                    @include('template.components.tablefilter-sidebar-search')
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-9">
            <div class="card mb-3">
                <div class="card-body no-datatable-search">
                    <table
                            class="table table-striped table-bordered table-hover"
                            dusk="table-accounts"
                            id="table"
                            role="grid"
                    ></table>
                </div>
            </div>
        </div>
    </div>

    @include('template.components.model-delete-form')
@stop
