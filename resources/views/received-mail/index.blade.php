@extends('template.layouts.page')

@section('title_postfix',  __('Received emails'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Emails received by YAFFA'))

@section('content')
    <div class="row">
        <div class="col-12 col-lg-3">
            {{-- Placeholder for any actions to be introduced in the future
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
                               data-test="button-new-account"
                               href="{{ route('account-entity.create', ['type' => 'account']) }}"
                               title="{{ __('New account') }}"
                            >
                                <i class="fa fa-plus"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
           --}}
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
                <ul class="list-group list-group-flush collapse show" id="cardFilters">
                    <x-tablefilter-sidebar-switch
                            label=" {{ __('Processed') }}"
                            property="processed"
                    />
                    <x-tablefilter-sidebar-switch
                            label=" {{ __('Handled') }}"
                            property="handled"
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
                            data-test="table-received-mails"
                            id="table"
                    ></table>
                </div>
            </div>
        </div>
    </div>

    <div id="app">
        <transaction-show-modal></transaction-show-modal>
    </div>
@stop
