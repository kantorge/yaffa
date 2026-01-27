@extends('template.layouts.page')

@section('title_postfix', __('Account Reconciliation'))

@section('content_header')
    {{ __('Account Reconciliation') }} - {{ $account->name }}
@stop

@section('content')
<div class="container-fluid">
    <!-- Date Range Selection Card -->
    <div class="card mb-3">
        <div class="card-header">
            <div class="card-title">
                {{ __('Reconciliation Period') }}
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('account.reconcile', $account) }}" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">{{ __('Start Date') }}</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}" required>
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">{{ __('End Date') }}</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-refresh"></i> {{ __('Update Period') }}
                    </button>
                    <a href="{{ route('account.history', $account) }}" class="btn btn-secondary">
                        <i class="fa fa-list"></i> {{ __('Full History') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Balance Summary Card -->
    <div class="card mb-3">
        <div class="card-header">
            <div class="card-title">
                {{ __('Balance Summary') }}
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="p-3 bg-light border rounded">
                        <h6 class="text-muted mb-1">{{ __('Opening Balance') }}</h6>
                        <h4 class="mb-0">
                            {{ number_format($openingBalance, 2) }} 
                            <small class="text-muted">{{ $account->config->currency->iso_code ?? 'USD' }}</small>
                        </h4>
                        <small class="text-muted">{{ __('As of') }} {{ $startDate }}</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light border rounded">
                        <h6 class="text-muted mb-1">{{ __('Closing Balance') }}</h6>
                        <h4 class="mb-0">
                            {{ number_format($closingBalance, 2) }} 
                            <small class="text-muted">{{ $account->config->currency->iso_code ?? 'USD' }}</small>
                        </h4>
                        <small class="text-muted">{{ __('As of') }} {{ $endDate }}</small>
                        @if(!empty($checkpoint))
                            <div class="mt-2">
                                <small class="text-muted">{{ __('Checkpoint Balance:') }}</small>
                                <strong>{{ number_format($checkpoint->balance, 2) }}</strong>
                                <small class="text-muted">{{ $account->config->currency->iso_code ?? 'USD' }}</small>
                                @if(!empty($checkpointMatches))
                                    <span class="badge bg-success ms-2">{{ __('Matches') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark ms-2">{{ __('Mismatch') }}</span>
                                @endif
                                <div>
                                    <small class="text-muted">{{ __('Variance:') }}</small>
                                    <strong class="{{ ($checkpointVariance >= 0) ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($checkpointVariance, 2) }}
                                    </strong>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light border rounded">
                        <h6 class="text-muted mb-1">{{ __('Net Change') }}</h6>
                        <h4 class="mb-0 {{ ($closingBalance - $openingBalance) >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($closingBalance - $openingBalance, 2) }} 
                            <small class="text-muted">{{ $account->config->currency->iso_code ?? 'USD' }}</small>
                        </h4>
                        <small class="text-muted">{{ __('Change in period') }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Card -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <div class="card-title">
                {{ __('Transactions in Period') }}
            </div>
            <div>
                <button 
                    type="button" 
                    class="btn btn-warning" 
                    id="openBalanceCheckpointButton"
                    onclick="openBalanceCheckpointModal()"
                    title="{{ __('Create Balance Checkpoint') }}">
                    <i class="fa fa-flag-checkered"></i> {{ __('Create Checkpoint') }}
                </button>
                <a href="{{ route('transaction.create', ['type' => 'standard', 'account_from' => $account->id ]) }}" class="btn btn-success" title="{{ __('New transaction') }}"><i class="fa fa-cart-plus"></i></a>
                @if($account->config_type === 'account')
                    <a href="{{ route('transaction.create', ['type' => 'investment', 'account' => $account->id ]) }}" class="btn btn-success" title="{{ __('New investment transaction') }}"><i class="fa fa-line-chart"></i></a>
                @endif
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-hover no-footer" id="reconcileTable"></table>
        </div>
    </div>
</div>

<div id="app">
    <transaction-show-modal></transaction-show-modal>
    <balance-checkpoint-modal 
        :account-entity-id="{{ $account->id }}"
        :current-balance="{{ $closingBalance ?? 0 }}"
        currency-code="{{ $account->config->currency->iso_code ?? 'USD' }}"
        :suggested-date="'{{ $endDate }}'"
    ></balance-checkpoint-modal>
</div>

@include('template.components.model-delete-form')

<script>
function openBalanceCheckpointModal() {
    try {
        const el = document.getElementById('balanceCheckpointModal');
        if (!el) return console.warn('balanceCheckpointModal element not found');
        // Use Bootstrap's Modal API to create/show the modal instance
        const Modal = window.bootstrap?.Modal || (window.bootstrap && window.bootstrap.Modal) || null;
        if (!Modal) {
            console.warn('Bootstrap Modal not available on window.bootstrap');
            return;
        }
        const instance = Modal.getOrCreateInstance(el);
        instance.show();
    } catch (err) {
        console.error('Failed to open balance checkpoint modal:', err);
    }
}
</script>

@stop
