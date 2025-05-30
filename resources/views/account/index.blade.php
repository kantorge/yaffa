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
                            class="card-title collapse-control
                                @handheld collapsed @endhandheld"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardActions"
                    >
                        <i class="fa fa-angle-down"></i>
                        {{ __('Actions') }}
                    </div>
                </div>
                <ul
                        class="list-group list-group-flush collapse
                            @desktop show @enddesktop"
                        aria-expanded="@desktop true @elsedesktop false @enddesktop"
                        id="cardActions"
                >
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('New account') }}
                        <a class="btn btn-success btn-sm"
                           dusk="button-new-account"
                           href="{{ route('account-entity.create', ['type' => 'account']) }}"
                           title="{{ __('New account') }}"
                        >
                            <i class="fa fa-plus"></i>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <div
                            class="card-title collapse-control
                                @handheld collapsed @endhandheld"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardFilters"
                    >
                        <i class="fa fa-angle-down"></i>
                        {{ __('Filters') }}
                    </div>
                </div>
                <ul
                        class="list-group list-group-flush collapse
                            @desktop show @enddesktop"
                        aria-expanded="@desktop true @elsedesktop false @enddesktop"
                        id="cardFilters">
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
                            class="table table-bordered table-hover"
                            dusk="table-accounts"
                            id="table"
                    ></table>
                </div>
            </div>
        </div>
    </div>
@stop
