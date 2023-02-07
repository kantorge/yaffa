@extends('template.layouts.page')

@section('title_postfix', __('Account details'))

@section('content_header')
    {{ __('Account details for ":account"', ['account' => $account->name]) }}
@stop

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-3">
                <div class="card mb-3">
                    <div class="card-header">
                        <div
                                class="card-title collapse-control"
                                data-coreui-toggle="collapse"
                                data-coreui-target="#cardOverview"
                        >
                            <i class="fa fa-angle-down"></i>
                            {{ __('Overview') }}
                        </div>
                    </div>
                    <div class="collapse card-body show" aria-expanded="true" id="cardOverview">
                        <dl class="row mb-0">
                            <dt class="col-8">{{ __('Active') }}</dt>
                            <dd class="col-4">@if ($account->active)
                                    <i class="fa fa-check-square text-success" title="' . __('Yes') . '"></i>
                                @else
                                    <i class="fa fa-square text-danger" title="' . __('No') . '"></i>
                                @endif</dd>
                            <dt class="col-8">{{ __('Currency') }}</dt>
                            <dd class="col-4">{{ $account->config->currency->suffix }}</dd>
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
                        <div
                                class="card-title collapsed collapse-control"
                                data-coreui-toggle="collapse"
                                data-coreui-target="#cardActions"
                        >
                            <i class="fa fa-angle-down"></i>
                            {{ __('Actions') }}
                        </div>
                    </div>
                    <div class="collapse card-body" aria-expanded="true" id="cardActions">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link"
                                   href="{{ route('account.history', ['account' => $account]) }}">{{ __('Load account transaction history') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link"
                                   href="{{ route('reports.cashflow', ['account' => $account->id]) }}">{{ __('Show account monthly history') }}</a>
                            </li>
                        </ul>
                    </div>
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

                                <input type="radio" class="btn-check" name="reconciled" id="reconciled_any" value=""
                                       checked>
                                <label class="btn btn-sm btn-outline-primary" for="reconciled_any"
                                       title="{{ __('Any') }}">
                                    <span class="fa fa-fw fa-circle"></span>
                                </label>

                                <input type="radio" class="btn-check" name="reconciled" id="reconciled_no"
                                       value="{{ __('Uncleared') }}">
                                <label class="btn btn-sm btn-outline-primary" for="reconciled_no"
                                       title="{{ __('Uncleared') }}">
                                    <span class="fa fa-fw fa-close"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="card-title">
                            {{ __('Date') }}
                        </div>
                    </div>
                    <div class="card-body" id="dateRangePicker">
                        <div class="row">
                            <div class="col-6">
                                <label for="date_from" class="form-label">{{ __('Date from') }}</label>
                                <input type="text" class="form-control" name="date_from" id="date_from"
                                       placeholder="{{ __('Select date') }}" autocomplete="off">
                            </div>
                            <div class="col-6">
                                <label for="date_to" class="form-label">{{ __('Date to') }}</label>
                                <input type="text" class="form-control" name="date_to" id="date_to"
                                       placeholder="{{ __('Select date') }}" autocomplete="off">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <select id="dateRangePickerPresets" class="form-select">
                                    <option value="placeholder">{{ __('Select preset') }}</option>
                                    <option value="thisMonth">{{ __('This month') }}</option>
                                    <option value="thisQuarter">{{ __('This quarter') }}</option>
                                    <option value="thisYear">{{ __('This year') }}</option>
                                    <option value="thisMonthToDate">{{ __('This month to date') }}</option>
                                    <option value="thisQuarterToDate">{{ __('This quarter to date') }}</option>
                                    <option value="thisYearToDate">{{ __('This year to date') }}</option>
                                    <option value="previousMonth">{{ __('Previous month') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button class="btn btn-sm btn-outline-dark"
                                id="clear_dates"
                        >{{ __('Clear selection') }}</button>
                        <button name="reload" type="button" id="reload"
                                class="btn btn-sm btn-primary ms-2"
                        >{{ __('Update') }}</button>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-9">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">
                        <div class="card-title">
                            {{ __('Transaction history') }}
                        </div>
                        <div>
                            <button type="button" id="create-standard-transaction-button" class="btn btn-sm btn-success"
                                    title="{{ __('New transaction') }}"><i class="fa fa-cart-plus"></i></button>
                            <a href="{{ route('transactions.createInvestment', ['account' => $account->id ]) }}"
                               class="btn btn-sm  btn-success" title="{{ __('New investment transaction') }}"><i
                                        class="fa fa-line-chart"></i></a>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-hover no-footer" id="historyTable"></table>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <div class="card-title">
                            {{ __('Scheduled transactions') }}
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-hover no-footer" id="scheduleTable"></table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="app">
        <transaction-show-modal></transaction-show-modal>
        <transaction-create-modal></transaction-create-modal>
    </div>

    @include('template.components.model-delete-form')

@stop
