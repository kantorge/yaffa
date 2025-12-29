@extends('template.layouts.page')

@section('title_postfix', __('Test Import Rules'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Test Import Rules'))

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0">{{ __('Test Configuration') }}</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('transaction-import-rules.test') }}" class="row g-3">
            <div class="col-md-4">
                <label for="rule_id" class="form-label">{{ __('Test Specific Rule') }}</label>
                <select name="rule_id" id="rule_id" class="form-select">
                    <option value="">{{ __('All Active Rules') }}</option>
                    @foreach($allRules as $rule)
                        <option value="{{ $rule->id }}" {{ request('rule_id') == $rule->id ? 'selected' : '' }}>
                            #{{ $rule->priority }} - {{ $rule->description_pattern }}
                            @if(!$rule->active) ({{ __('Inactive') }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="days_back" class="form-label">{{ __('Search Period (days)') }}</label>
                <input type="number" name="days_back" id="days_back" class="form-control" 
                       value="{{ $daysBack }}" min="1">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> {{ __('Search') }}
                </button>
                <a href="{{ route('transaction-import-rules.index') }}" class="btn btn-secondary ms-2">
                    <i class="fa fa-arrow-left"></i> {{ __('Back to Rules') }}
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                {{ __('Matching Transactions') }}
                <span class="badge bg-primary">{{ count($matches) }}</span>
            </h6>
            @if(count($matches) > 0)
                <button type="button" class="btn btn-success btn-sm" onclick="applyAllCorrections()">
                    <i class="fa fa-check"></i> {{ __('Apply All Corrections') }}
                </button>
            @endif
        </div>
    </div>
    <div class="card-body">
        @if(isset($debugInfo) && config('app.debug'))
            <div class="alert alert-info small mb-3">
                <strong>Debug Info:</strong>
                Total transactions found: {{ $debugInfo['total_transactions'] }} |
                Transactions checked: {{ $debugInfo['transactions_checked'] }} |
                Rules tested: {{ $debugInfo['total_rules'] }}
            </div>
        @endif
        
        @if(empty($matches))
            <p class="text-muted mb-0">
                {{ __('No transactions found matching the selected rules in the last :days days.', ['days' => $daysBack]) }}
            </p>
            @if(isset($debugInfo) && $debugInfo['total_transactions'] == 0)
                <p class="text-warning mt-2">
                    <i class="fa fa-exclamation-triangle"></i>
                    {{ __('No standard transactions found in this period. Make sure you have non-scheduled, non-budget transactions.') }}
                </p>
            @endif
        @else
            <form id="corrections-form" method="POST" action="{{ route('transaction-import-rules.apply') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="select-all" class="form-check-input">
                                </th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Matched Rule') }}</th>
                                <th>{{ __('Suggested Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matches as $index => $match)
                                @php
                                    $transaction = $match['transaction'];
                                    $rule = $match['rule'];
                                    $description = $match['description'];
                                    $config = $transaction->config;
                                    
                                    // Determine current accounts
                                    $accountFrom = $config->accountFrom?->name ?? '-';
                                    $accountTo = $config->accountTo?->name ?? '-';
                                    
                                    // Calculate display amount - use the non-zero amount from config
                                    $amount = $config->amount_from ?: $config->amount_to;
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox" 
                                               name="corrections[{{ $index }}][apply]" 
                                               class="form-check-input correction-checkbox"
                                               value="1"
                                               data-transaction-id="{{ $transaction->id }}"
                                               data-rule-id="{{ $rule->id }}">
                                        <input type="hidden" 
                                               name="corrections[{{ $index }}][transaction_id]" 
                                               value="{{ $transaction->id }}">
                                        <input type="hidden" 
                                               name="corrections[{{ $index }}][rule_id]" 
                                               value="{{ $rule->id }}">
                                    </td>
                                    <td>{{ $transaction->date->format('Y-m-d') }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ Str::limit($description, 50) }}</strong>
                                        </div>
                                        <small class="text-muted">
                                            {{ __('From') }}: {{ $accountFrom }} → 
                                            {{ __('To') }}: {{ $accountTo }}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ $transaction->transactionType->name }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($amount, 2) }}</td>
                                    <td>
                                        <div>
                                            <code class="small">{{ $rule->description_pattern }}</code>
                                        </div>
                                        <small class="text-muted">Priority: {{ $rule->priority }}</small>
                                    </td>
                                    <td>
                                        @if($rule->action === 'convert_to_transfer')
                                            <span class="badge bg-primary">
                                                <i class="fa fa-exchange-alt"></i> 
                                                {{ __('Convert to Transfer') }}
                                            </span>
                                            <div class="small text-muted mt-1">
                                                → {{ $rule->transferAccount?->name ?? __('Unknown') }}
                                            </div>
                                        @elseif($rule->action === 'merge_payee')
                                            <span class="badge bg-success">
                                                <i class="fa fa-object-group"></i> 
                                                {{ __('Merge Payee') }}
                                            </span>
                                            <div class="small text-muted mt-1">
                                                → {{ $rule->mergePayee?->name ?? __('Unknown') }}
                                                @if($rule->append_original_to_comment)
                                                    <i class="fa fa-comment ms-1" title="{{ __('Will append original to comment') }}"></i>
                                                @endif
                                            </div>
                                        @elseif($rule->action === 'skip')
                                            <span class="badge bg-danger">
                                                <i class="fa fa-ban"></i> 
                                                {{ __('Mark as Skip') }}
                                            </span>
                                        @elseif($rule->action === 'modify')
                                            <span class="badge bg-warning text-dark">
                                                <i class="fa fa-edit"></i> 
                                                {{ __('Modify Type') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span id="selected-count" class="text-muted">0</span> {{ __('transaction(s) selected') }}
                    </div>
                    <button type="submit" class="btn btn-success" id="apply-selected-btn" disabled>
                        <i class="fa fa-check"></i> {{ __('Apply Selected Corrections') }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fa fa-info-circle"></i> {{ __('About Rule Testing') }}
        </h6>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li>{{ __('This tool tests your import rules against existing transactions to see which ones would match.') }}</li>
            <li>{{ __('Select transactions to apply the rule\'s action (convert to transfer, mark for skip, etc.).') }}</li>
            <li>{{ __('Changes are applied immediately to selected transactions when you click "Apply".') }}</li>
            <li>{{ __('Only active rules are tested by default. Use the dropdown to test a specific rule.') }}</li>
            <li><strong>{{ __('Warning:') }}</strong> {{ __('Applying corrections modifies your existing transactions. This cannot be easily undone.') }}</li>
        </ul>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Global function for "Apply All" button
function applyAllCorrections() {
    const form = document.getElementById('corrections-form');
    if (!form) {
        console.error('Corrections form not found');
        return;
    }
    
    // Check all checkboxes
    const checkboxes = document.querySelectorAll('.correction-checkbox');
    if (checkboxes.length === 0) {
        alert('{{ __("No transactions to correct.") }}');
        return;
    }
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    
    // Update UI
    const selectAll = document.getElementById('select-all');
    const selectedCount = document.getElementById('selected-count');
    const applyBtn = document.getElementById('apply-selected-btn');
    
    if (selectAll) selectAll.checked = true;
    if (selectedCount) selectedCount.textContent = checkboxes.length;
    if (applyBtn) applyBtn.disabled = false;
    
    // Submit form
    if (confirm('{{ __("Apply corrections to ALL matching transactions? This will modify your existing transactions.") }}')) {
        form.submit();
    } else {
        // Uncheck everything if user cancels
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        if (selectAll) selectAll.checked = false;
        if (selectedCount) selectedCount.textContent = '0';
        if (applyBtn) applyBtn.disabled = true;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const correctionCheckboxes = document.querySelectorAll('.correction-checkbox');
    const selectedCountSpan = document.getElementById('selected-count');
    const applySelectedBtn = document.getElementById('apply-selected-btn');
    
    // Only set up event listeners if we have the necessary elements
    if (!correctionCheckboxes.length || !selectedCountSpan || !applySelectedBtn) {
        return;
    }
    
    // Update selected count
    function updateSelectedCount() {
        const checkedCount = document.querySelectorAll('.correction-checkbox:checked').length;
        if (selectedCountSpan) {
            selectedCountSpan.textContent = checkedCount;
        }
        if (applySelectedBtn) {
            applySelectedBtn.disabled = checkedCount === 0;
        }
        
        // Update select all checkbox state
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedCount === correctionCheckboxes.length && checkedCount > 0;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < correctionCheckboxes.length;
        }
    }
    
    // Select/deselect all
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            correctionCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    // Update count when individual checkboxes change
    correctionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // Initialize count on page load
    updateSelectedCount();
    
    // Confirmation before applying
    const form = document.getElementById('corrections-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const checkedCount = document.querySelectorAll('.correction-checkbox:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                alert('{{ __("Please select at least one transaction to correct.") }}');
                return false;
            }
            
            const message = '{{ __("Are you sure you want to apply corrections to :count transaction(s)? This will modify your existing transactions.") }}'.replace(':count', checkedCount);
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>
@endpush
