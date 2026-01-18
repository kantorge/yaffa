@extends('template.layouts.page')

@section('title_postfix', __('Account history'))

@section('content_header')
    {{ __('Account history') }} - {{ $account->name }}
@stop

@section('content')
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <div class="card-title">
                {{ __('Transaction history') }}
            </div>
            <div>
                <div class="d-inline-block">
                    <div class="btn-group" data-toggle="buttons">
                        <label class="btn btn-primary" title="{{ __('Show reconciled only') }}">
                            <input type="radio" name="reconciled" value="Reconciled" class="btn-check">
                            <span class="fa fa-fw fa-check"></span>
                        </label>
                        <label class="btn btn-primary active" title="{{ __('Show all transactions') }}">
                            <input type="radio" name="reconciled" value="" class="btn-check" checked="checked">
                            <span class="fa fa-fw fa-circle"></span>
                        </label>
                        <label class="btn btn-primary" title="{{ __('Show uncleared only') }}">
                            <input type="radio" name="reconciled" value="Uncleared" class="btn-check">
                            <span class="fa fa-fw fa-close"></span>
                        </label>
                    </div>
                </div>
                <a
                    class="btn {{($withForecast ? 'btn-primary' : 'btn-info') }}"
                    href="{{ route('account.history', ['account' => $account->id, 'withForecast' => ($withForecast ? '' : 'withForecast')]) }}"
                    title="{{ $withForecast ? __('Without forecast') : __('With forecast') }}">
                    <i class="fa fa-calendar"></i>
                </a>
                <button 
                    type="button" 
                    class="btn btn-warning" 
                    data-bs-toggle="modal" 
                    data-bs-target="#balanceCheckpointModal"
                    title="{{ __('Balance Checkpoints') }}">
                    <i class="fa fa-flag-checkered"></i>
                </button>
                <a href="{{ route('transaction.create', ['type' => 'standard', 'account_from' => $account->id ]) }}" class="btn btn-success" title="{{ __('New transaction') }}"><i class="fa fa-cart-plus"></i></a>
                <a href="{{ route('transaction.create', ['type' => 'investment', 'account' => $account->id ]) }}" class="btn btn-success" title="{{ __('New investment transaction') }}"><i class="fa fa-line-chart"></i></a>
                @if($account->config_type === 'account')
                    <a href="{{ route('account.batch-entry.investment', $account) }}" class="btn btn-primary" title="{{ __('Batch entry - Investment') }}"><i class="fa fa-list"></i></a>
                    <a href="{{ route('account.batch-reconcile.investment', $account) }}" class="btn btn-info" title="{{ __('Batch reconcile - Investment') }}"><i class="fa fa-check-circle"></i></a>
                @endif
                    <a href="{{ route('transaction-import-rules.create', ['description_pattern' => $account->name]) }}" class="btn btn-warning" title="{{ __('Create import rule for this account') }}">
                        <i class="fa fa-filter"></i>
                    </a>
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

<div id="app">
    <transaction-show-modal></transaction-show-modal>
    <balance-checkpoint-modal 
        :account-entity-id="{{ $account->id }}"
        :current-balance="{{ $account->balance ?? 0 }}"
        currency-code="{{ $account->config->currency->iso_code ?? 'USD' }}"
    ></balance-checkpoint-modal>
</div>

@include('template.components.model-delete-form')
@include('template.components.transaction-skip-form')

@stop
