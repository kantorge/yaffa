@extends('template.layouts.page')

@section('title_postfix', __('Transaction Import Rules'))

@section('content_container_classes', 'container-lg')

@section('content_header', isset($rule) ? __('Edit Import Rule') : __('Create Import Rule'))

@section('content')

@if(isset($rule))
<form action="{{ route('transaction-import-rules.update', $rule) }}" method="POST" autocomplete="off">
    @method('PATCH')
@else
<form action="{{ route('transaction-import-rules.store') }}" method="POST" autocomplete="off">
@endif
    @csrf

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">{{ isset($rule) ? __('Edit Rule') : __('New Rule') }}</h5>
        </div>
        <div class="card-body">
            
            <!-- Priority -->
            <div class="row mb-3">
                <label for="priority" class="col-form-label col-sm-3">
                    {{ __('Priority') }} <span class="text-danger">*</span>
                </label>
                <div class="col-sm-9">
                    <input type="number" 
                           class="form-control @error('priority') is-invalid @enderror" 
                           id="priority" 
                           name="priority" 
                           value="{{ old('priority', $rule->priority ?? 100) }}"
                           min="1"
                           required>
                    <small class="form-text text-muted">{{ __('Lower numbers are processed first (e.g., 10 before 100)') }}</small>
                    @error('priority')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Account -->
            <div class="row mb-3">
                <label for="account_id" class="col-form-label col-sm-3">
                    {{ __('Apply to Account') }}
                </label>
                <div class="col-sm-9">
                    <select class="form-select @error('account_id') is-invalid @enderror" 
                            id="account_id" 
                            name="account_id">
                        <option value="">{{ __('All Accounts (Global Rule)') }}</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" 
                                    {{ old('account_id', $rule->account_id ?? '') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('Leave blank for global rule, or select specific account') }}</small>
                    @error('account_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Description Pattern -->
            <div class="row mb-3">
                <label for="description_pattern" class="col-form-label col-sm-3">
                    {{ __('Description Pattern') }} <span class="text-danger">*</span>
                </label>
                <div class="col-sm-9">
                    <input type="text" 
                           class="form-control @error('description_pattern') is-invalid @enderror" 
                           id="description_pattern" 
                           name="description_pattern" 
                           value="{{ old('description_pattern', $rule->description_pattern ?? '') }}"
                           required>
                    <small class="form-text text-muted">{{ __('Text to match in transaction descriptions (case-insensitive)') }}</small>
                    @error('description_pattern')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Use Regex -->
            <div class="row mb-3">
                <label for="use_regex" class="col-form-label col-sm-3">
                    {{ __('Use Regex') }}
                </label>
                <div class="col-sm-9">
                    <div class="form-check form-switch">
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="use_regex" 
                               name="use_regex"
                               {{ old('use_regex', $rule->use_regex ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="use_regex">
                            {{ __('Enable regular expression matching') }}
                        </label>
                    </div>
                    <small class="form-text text-muted">{{ __('Enable for advanced pattern matching (e.g., /^PAYPAL.*/i)') }}</small>
                </div>
            </div>

            <hr>

            <!-- Action -->
            <div class="row mb-3">
                <label for="action" class="col-form-label col-sm-3">
                    {{ __('Action') }} <span class="text-danger">*</span>
                </label>
                <div class="col-sm-9">
                    <select class="form-select @error('action') is-invalid @enderror" 
                            id="action" 
                            name="action"
                            required>
                        <option value="convert_to_transfer" {{ old('action', $rule->action ?? '') == 'convert_to_transfer' ? 'selected' : '' }}>
                            {{ __('Convert to Transfer') }}
                        </option>
                        <option value="skip" {{ old('action', $rule->action ?? '') == 'skip' ? 'selected' : '' }}>
                            {{ __('Skip (Don\'t Import)') }}
                        </option>
                    </select>
                    @error('action')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Transfer Account (shown when action is convert_to_transfer) -->
            <div class="row mb-3" id="transfer-account-row">
                <label for="transfer_account_id" class="col-form-label col-sm-3">
                    {{ __('Transfer To Account') }}
                </label>
                <div class="col-sm-9">
                    <select class="form-select @error('transfer_account_id') is-invalid @enderror" 
                            id="transfer_account_id" 
                            name="transfer_account_id">
                        <option value="">{{ __('Select Account') }}</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" 
                                    {{ old('transfer_account_id', $rule->transfer_account_id ?? '') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">{{ __('The other account involved in the transfer') }}</small>
                    @error('transfer_account_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Transaction Type -->
            <div class="row mb-3" id="transaction-type-row">
                <label for="transaction_type_id" class="col-form-label col-sm-3">
                    {{ __('Transaction Type') }}
                </label>
                <div class="col-sm-9">
                    <select class="form-select @error('transaction_type_id') is-invalid @enderror" 
                            id="transaction_type_id" 
                            name="transaction_type_id">
                        <option value="3" {{ old('transaction_type_id', $rule->transaction_type_id ?? 3) == 3 ? 'selected' : '' }}>
                            {{ __('Transfer') }}
                        </option>
                        <option value="1" {{ old('transaction_type_id', $rule->transaction_type_id ?? 3) == 1 ? 'selected' : '' }}>
                            {{ __('Withdrawal') }}
                        </option>
                        <option value="2" {{ old('transaction_type_id', $rule->transaction_type_id ?? 3) == 2 ? 'selected' : '' }}>
                            {{ __('Deposit') }}
                        </option>
                    </select>
                    <small class="form-text text-muted">{{ __('Usually "Transfer" for account-to-account movements') }}</small>
                    @error('transaction_type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Active -->
            <div class="row mb-3">
                <label for="active" class="col-form-label col-sm-3">
                    {{ __('Status') }}
                </label>
                <div class="col-sm-9">
                    <div class="form-check form-switch">
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="active" 
                               name="active"
                               {{ old('active', $rule->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">
                            {{ __('Active') }}
                        </label>
                    </div>
                    <small class="form-text text-muted">{{ __('Inactive rules are ignored during import') }}</small>
                </div>
            </div>

        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-save"></i> {{ __('Save Rule') }}
            </button>
            <a href="{{ route('transaction-import-rules.index') }}" class="btn btn-secondary">
                {{ __('Cancel') }}
            </a>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionSelect = document.getElementById('action');
    const transferAccountRow = document.getElementById('transfer-account-row');
    const transactionTypeRow = document.getElementById('transaction-type-row');

    function toggleFields() {
        const isTransfer = actionSelect.value === 'convert_to_transfer';
        transferAccountRow.style.display = isTransfer ? 'flex' : 'none';
        transactionTypeRow.style.display = isTransfer ? 'flex' : 'none';
    }

    actionSelect.addEventListener('change', toggleFields);
    toggleFields(); // Initialize on page load
});
</script>

@endsection
