@extends('template.layouts.page')

@section('title_postfix', __('Account details'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
{{ __('Account details for ":account"', ['account' => $account->name]) }}
@stop

@section('content')
<div class="row">
    <div class="col-12 col-lg-3" id="accountLeftControlPanel">
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title collapse-control" data-coreui-toggle="collapse"
                    data-coreui-target="#cardOverview">
                    <i class="fa fa-angle-down"></i>
                    {{ __('Overview') }}
                </div>
            </div>
            <div class="collapse card-body show" aria-expanded="true" id="cardOverview">
                <dl class="row mb-0">
                    <dt class="col-8">{{ __('Active') }}</dt>
                    <dd class="col-4">
                        @if ($account->active)
                            <i class="fa fa-check-square text-success" title="{{ __('Yes') }}"></i>
                        @else
                            <i class="fa fa-square text-danger" title="{{ __('No') }}"></i>
                        @endif
                    </dd>
                    <dt class="col-8">{{ __('Currency') }}</dt>
                    <dd class="col-4">{{ $account->config->currency->iso_code }}</dd>
                    <dt class="col-8">{{ __('Opening balance') }}</dt>
                    <dd class="col-4" id="overviewOpeningBalance"><i class="fa fa-spin fa-spinner"></i></dd>
                    <dt class="col-8">{{ __('Current cash value') }}</dt>
                    <dd class="col-4" id="overviewCurrentCash"><i class="fa fa-spin fa-spinner"></i></dd>
                    <dt class="col-8">{{ __('Current balance with investments') }}</dt>
                    <dd class="col-4" id="overviewCurrentBalance"><i class="fa fa-spin fa-spinner"></i></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title collapsed collapse-control" data-coreui-toggle="collapse"
                    data-coreui-target="#cardActions">
                    <i class="fa fa-angle-down"></i>
                    {{ __('Actions') }}
                </div>
            </div>
            <ul class="list-group list-group-flush collapse" aria-expanded="false" id="cardActions">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a class="nav-link" href="{{ route('account.history', ['account' => $account]) }}">
                        {{ __('Load account transaction history') }}
                    </a>
                    <i class="fa-solid fa-clock text-warning" data-bs-toggle="tooltip"
                        title="{{ __('This page can load slowly based on the number of transactions.') }}"></i>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <a class="nav-link" href="{{ route('reports.cashflow', ['accountEntity' => $account->config->id]) }}">
                        {{ __('Show account monthly history') }}
                    </a>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="nav-link">
                        {{ __('Recalculate monthly cached data') }}
                    </span>
                    <button class="btn btn-sm btn-outline-primary" id="recalculateMonthlyCachedData">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </button>
                </li>
            </ul>
        </div>

        <h2>{{ __('Transaction filters') }}</h2>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <div class="card-title">
                    {{ __('Reconciled') }}
                </div>
                <div>
                    <div class="btn-group" role="group" aria-label="Toggle button group for reconciled state">
                        <input type="radio" class="btn-check" name="reconciled" id="reconciled_yes"
                            value="{{ __('Reconciled') }}">
                        <label class="btn btn-sm btn-outline-primary" for="reconciled_yes"
                            title="{{ __('Reconciled') }}">
                            <span class="fa fa-fw fa-check"></span>
                        </label>

                        <input type="radio" class="btn-check" name="reconciled" id="reconciled_any" value="" checked>
                        <label class="btn btn-sm btn-outline-primary" for="reconciled_any" title="{{ __('Any') }}">
                            <span class="fa fa-fw fa-circle"></span>
                        </label>

                        <input type="radio" class="btn-check" name="reconciled" id="reconciled_no"
                            value="{{ __('Uncleared') }}">
                        <label class="btn btn-sm btn-outline-primary" for="reconciled_no" title="{{ __('Uncleared') }}">
                            <span class="fa fa-fw fa-close"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div id="account-date-range-filter">
            <date-range-filter-card
                :expanded="true"
                :show-update-button="true"
                component-id="accountDate"
                :initial-date-from="initialDateFrom"
                :initial-date-to="initialDateTo"
                :initial-preset="initialPreset"
                :update-url="true"
                ref="dateFilter"
                @update="onDateRangeUpdated"
            ></date-range-filter-card>
        </div>
    </div>

    <div class="col-12 col-lg-9" id="accountMainContent">
        <div class="left-control-panel-toggle-shell mb-3">
            <button
                type="button"
                id="toggleAccountLeftControlPanelButton"
                class="btn btn-sm btn-outline-secondary left-control-panel-toggle-handle"
                title="{{ __('Collapse left control panel') }}"
                aria-label="{{ __('Collapse left control panel') }}"
                aria-expanded="true"
                aria-controls="accountLeftControlPanel accountMainContent"
            >
                <i class="fas fa-angles-left" data-left-control-panel-toggle-icon></i>
            </button>
            <div class="card mb-3" id="advancedReconcileCard">
                <div class="card-header">
                    <div class="card-title collapsed collapse-control" data-coreui-toggle="collapse"
                        data-coreui-target="#advancedReconcileBody">
                        <i class="fa fa-angle-down"></i>
                        {{ __('Advanced reconcile') }}
                    </div>
                </div>
                <div class="collapse" aria-expanded="false" id="advancedReconcileBody">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-xl-4">
                                <h3 class="h6">{{ __('Cash') }}</h3>
                                <dl class="row mb-2" data-reconcile-section="cash"></dl>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-checkpoint-type="cash">
                                    <i class="fa-solid fa-bookmark"></i>
                                    {{ __('Set checkpoint') }}
                                </button>
                            </div>
                            <div class="col-12 col-xl-4">
                                <h3 class="h6">{{ __('Investments') }}</h3>
                                <dl class="row mb-2" data-reconcile-section="investment"></dl>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-checkpoint-type="investment">
                                    <i class="fa-solid fa-bookmark"></i>
                                    {{ __('Set checkpoint') }}
                                </button>
                            </div>
                            <div class="col-12 col-xl-4">
                                <h3 class="h6">{{ __('Total') }}</h3>
                                <dl class="row mb-2" data-reconcile-section="total"></dl>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-checkpoint-type="total">
                                    <i class="fa-solid fa-bookmark"></i>
                                    {{ __('Set checkpoint') }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-secondary collapsed" type="button"
                                data-coreui-toggle="collapse" data-coreui-target="#advancedReconcileHoldings">
                                <i class="fa-solid fa-layer-group"></i>
                                {{ __('Investment holdings') }}
                            </button>
                            <div class="collapse mt-2" id="advancedReconcileHoldings">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Investment') }}</th>
                                                <th>{{ __('Open qty') }}</th>
                                                <th>{{ __('Close qty') }}</th>
                                                <th>{{ __('Buys') }}</th>
                                                <th>{{ __('Sells') }}</th>
                                                <th>{{ __('Open price') }}</th>
                                                <th>{{ __('Close price') }}</th>
                                                <th>{{ __('Open value') }}</th>
                                                <th>{{ __('Close value') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="advancedReconcileHoldingsBody">
                                            <tr>
                                                <td colspan="7"><i class="fa fa-fw fa-spinner fa-spin"></i></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card left-control-panel-toggle-card">
                <div class="card-header d-flex justify-content-between left-control-panel-toggle-header">
                    <div class="card-title">
                        {{ __('Transaction history') }}
                    </div>
                    <div class="d-lg-none">
                        <a class="btn btn-sm btn-success" href="{{ route('transaction.create', ['type' => 'standard', 'callback' => 'back']) }}"
                            title="{{ __('New transaction') }}"><i class="fa fa-cart-plus"></i></a>
                        <a class="btn btn-sm btn-success" href="{{ route('transaction.create', ['type' => 'investment', 'callback' => 'back']) }}"
                            title="{{ __('New investment transaction') }}"><i class="fa fa-line-chart"></i></a>
                    </div>
                    <div class="d-none d-lg-block">
                        <button type="button" id="create-standard-transaction-button" class="btn btn-sm btn-success"
                            title="{{ __('New transaction') }}"><i class="fa fa-cart-plus"></i></button>
                        <button type="button" id="create-investment-transaction-button" class="btn btn-sm btn-success"
                            title="{{ __('New investment transaction') }}"><i class="fa fa-line-chart"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover no-footer" id="historyTable"></table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <div class="card-title">
                    {{ __('Scheduled transactions') }}
                </div>
                <div>
                    <a class="btn btn-sm btn-success"
                        href="{{ route('transaction.create', [
                            'type' => 'standard',
                            'account_from' => $account->id,
                            'schedule' => 1,
                            'callback' => 'back'
                        ]) }}"
                        title="{{ __('New scheduled transaction') }}"
                    >
                        <i class="fa fa-cart-plus"></i>
                    </a>
                    <a class="btn btn-sm btn-success"
                        href="{{ route('transaction.create', [
                            'type' => 'investment',
                            'account' => $account->id,
                            'schedule' => 1,
                            'callback' => 'back'
                        ]) }}"
                        title="{{ __('New scheduled investment transaction') }}"
                    >
                        <i class="fa fa-line-chart"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-hover no-footer" id="scheduleTable"></table>
            </div>
        </div>
    </div>
</div>

<div id="app">
    <transaction-show-modal></transaction-show-modal>
    <transaction-create-standard-modal></transaction-create-standard-modal>
    <transaction-create-investment-modal></transaction-create-investment-modal>
</div>

@include('template.components.model-delete-form')

@stop
