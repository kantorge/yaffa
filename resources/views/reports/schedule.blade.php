@extends('template.layouts.page')

@section('title_postfix', __('Scheduled transactions'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Scheduled and budgeted transactions'))

@section('content')
<div class="row">
    <div class="col-12 col-lg-3">
        <div id="onboarding-card">
            <onboarding-card
                    card-title="{{ __('Guided tour') }}"
                    completed-message="{{ __('You can dismiss this widget to hide it forever.') }}"
                    topic="ReportsSchedules"
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
                    {{ __('New scheduled, standard transaction') }}
                    <a class="btn btn-sm btn-success"
                       dusk="button-new-payee"
                       href="{{ route('transaction.create', [
                            'type' => 'standard',
                            'schedule' => '1',
                            'callback' => 'back'
                            ]) }}"
                       title="{{ __('New scheduled, standard transaction') }}"
                    >
                        <i class="fa fa-cart-plus"></i>
                    </a>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    {{ __('New scheduled, investment transaction') }}
                    <a class="btn btn-sm btn-success"
                       dusk="button-new-payee"
                       href="{{ route('transaction.create', [
                            'type' => 'investment',
                            'schedule' => '1',
                            'callback' => 'back'
                            ]) }}"
                       title="{{ __('New scheduled, investment transaction') }}"
                    >
                        <i class="fa fa-line-chart"></i>
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
                <x-tablefilter-sidebar-switch
                        label=" {{ __('Schedule') }}"
                        property="schedule"
                />
                <x-tablefilter-sidebar-switch
                        label=" {{ __('Budget') }}"
                        property="budget"
                />
                <x-tablefilter-sidebar-switch
                        label=" {{ __('Active') }}"
                        property="active"
                />
                @include('template.components.tablefilter-sidebar-search')
            </ul>
        </div>
    </div>
    <div class="col-12 col-lg-9">
        <div class="card">
            <div class="card-body no-datatable-search">
                <table
                        class="table table-bordered table-hover"
                        id="table"
                ></table>
            </div>
        </div>
    </div>
</div>

@stop
