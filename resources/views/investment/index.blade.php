@extends('template.layouts.page')

@section('title_postfix', __('Investments'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Investments'))

@section('content')
<div class="row">
    <div class="col-12 col-lg-3">
        <div id="onboarding-card">
            <onboarding-card card-title="{{ __('Guided tour') }}"
                completed-message="{{ __('You can dismiss this widget to hide it forever.') }}"
                topic="Investments"></onboarding-card>
        </div>
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title collapse-control" data-coreui-toggle="collapse"
                    data-coreui-target="#cardActions">
                    <i class="fa fa-angle-down"></i>
                    {{ __('Actions') }}
                </div>
            </div>
            <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardActions">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="{{ route('investment.create') }}">{{ __('New investment') }}</a>
                    <a class="btn btn-sm btn-success"
                        id="button-new-investment"
                        href="{{ route('investment.create') }}"
                        title="{{ __('New investment') }}">
                        <i class="fa fa-fw fa-plus"></i>
                    </a>
                </li>

                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="{{  route('transaction.create', ['type' => 'investment']) }}">
                        {{ __('New investment transaction') }}
                    </a>
                    <a class="btn btn-sm btn-success"
                        id="button-new-investment-transaction"
                        href="{{  route('transaction.create', ['type' => 'investment']) }}"
                        title="{{ __('New investment transaction') }}">
                        <i class="fa fa-fw fa-line-chart"></i>
                    </a>
                </li>

                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="{{  route('investment-group.index') }}">{{ __('Manage investment groups') }}</a>
                    <a class="btn btn-sm btn-outline-primary"
                        id="button-manage-investment-groups"
                        href="{{  route('investment-group.index') }}" title="{{ __('Manage investment groups') }}">
                        <i class="fa fa-fw fa-layer-group"></i>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title collapse-control" data-coreui-toggle="collapse"
                    data-coreui-target="#cardFilters">
                    <i class="fa fa-angle-down"></i>
                    {{ __('Filters') }}
                </div>
            </div>
            <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardFilters">
                <x-tablefilter-sidebar-switch label=" {{ __('Active') }}" property="active" />
                @include('template.components.tablefilter-sidebar-search')
                <li class="list-group-item">
                    <div id="investment-group-tree-container"></div>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-12 col-lg-9">
        <div class="card mb-3">
            <div class="card-body no-datatable-search">
                <table class="table table-striped table-bordered table-hover" id="investmentSummary" role="grid"
                    aria-label="{{ __('List of investments') }}"></table>
            </div>
        </div>
    </div>
</div>
@stop