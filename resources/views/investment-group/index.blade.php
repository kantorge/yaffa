@extends('template.layouts.page')

@section('title_postfix',  __('Investment Groups'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Investment Groups'))

@section('content')
    <div class="row">
        <div class="col-12 col-lg-3">
            <div id="onboarding-card">
                <onboarding-card
                        card-title="{{ __('Guided tour') }}"
                        completed-message="{{ __('You can dismiss this widget to hide it forever.') }}"
                        topic="InvestmentGroups"
                ></onboarding-card>
            </div>
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
                        {{ __('New investment group') }}
                        <a class="btn btn-sm btn-success"
                           data-test="button-new-investment-group"
                           href="{{ route('investment-group.create') }}"
                           title="{{ __('New investment group') }}"
                        >
                            <i class="fa fa-plus"></i>
                        </a>
                    </li>
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
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-9">
            <div class="card">
                <div class="card-body no-datatable-search">
                    <table
                            class="table table-striped table-bordered table-hover"
                            data-test="table-investment-groups"
                            id="table"
                    ></table>
                </div>
            </div>
        </div>
    </div>
@stop
