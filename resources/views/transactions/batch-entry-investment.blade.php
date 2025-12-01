@extends('template.layouts.page')

@section('title_postfix', __('Batch Entry - Investment Transactions'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Batch Entry - Investment Transactions') }}
    <small class="text-muted">{{ $account->name }}</small>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <strong>{{ __('Enter multiple investment transactions for :account', ['account' => $account->name]) }}</strong>
                </div>
                <div class="card-body">
                    @if($investments->isEmpty())
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i>
                            {{ __('No investments found in this account. Add some investment transactions first.') }}
                        </div>
                        <a href="{{ route('transaction.create', ['type' => 'investment']) }}" class="btn btn-primary">
                            <i class="fa fa-plus"></i> {{ __('Add Investment Transaction') }}
                        </a>
                    @else
                        <form action="{{ route('account.batch-entry.investment.store', $account) }}" method="POST" id="batchEntryForm">
                            @csrf
                            
                            <div class="mb-3">
                                <label for="date" class="form-label">{{ __('Transaction Date') }}</label>
                                <input type="date" class="form-control" id="date" name="date" 
                                    value="{{ old('date', now()->endOfMonth()->format('Y-m-d')) }}" required>
                                <div class="form-text">{{ __('Date for all transactions in this batch') }}</div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Investment') }}</th>
                                            <th class="text-end">{{ __('Current Qty') }}</th>
                                            <th class="text-end">{{ __('Last Price') }}</th>
                                            <th class="text-center">{{ __('Type') }}</th>
                                            <th class="text-end">{{ __('Quantity') }}</th>
                                            <th class="text-end">{{ __('Price') }}</th>
                                            <th class="text-end">{{ __('Commission') }}</th>
                                            <th class="text-end">{{ __('Tax') }}</th>
                                            <th class="text-end">{{ __('Subtotal') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($investments as $investment)
                                            <tr class="investment-row" data-investment-id="{{ $investment->id }}">
                                                <td>
                                                    <strong>{{ $investment->name }}</strong>
                                                    <br>
                                                    <small class="text-muted">{{ $investment->symbol }}</small>
                                                </td>
                                                <td class="text-end">
                                                    <span class="text-muted">{{ number_format($investment->current_quantity, 4) }}</span>
                                                </td>
                                                <td class="text-end">
                                                    @if($investment->latest_price)
                                                        <span class="text-muted">{{ number_format($investment->latest_price, 4) }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <select class="form-select form-select-sm transaction-type" 
                                                        name="transactions[{{ $loop->index }}][transaction_type]">
                                                        <option value="buy">{{ __('Buy') }}</option>
                                                        <option value="sell">{{ __('Sell') }}</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" 
                                                        class="form-control form-control-sm text-end quantity-input" 
                                                        name="transactions[{{ $loop->index }}][quantity]" 
                                                        value="0" 
                                                        placeholder="0.0000">
                                                    <input type="hidden" name="transactions[{{ $loop->index }}][investment_id]" value="{{ $investment->id }}">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" 
                                                        class="form-control form-control-sm text-end price-input" 
                                                        name="transactions[{{ $loop->index }}][price]" 
                                                        value="{{ $investment->latest_price ?? 0 }}" 
                                                        placeholder="0.0000">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" 
                                                        class="form-control form-control-sm text-end commission-input" 
                                                        name="transactions[{{ $loop->index }}][commission]" 
                                                        value="{{ $investment->last_commission ?? 0 }}" 
                                                        placeholder="0.00">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" 
                                                        class="form-control form-control-sm text-end tax-input" 
                                                        name="transactions[{{ $loop->index }}][tax]" 
                                                        value="0" 
                                                        placeholder="0.00">
                                                </td>
                                                <td class="text-end">
                                                    <strong class="subtotal" data-currency="{{ $account->config->currency->iso_code ?? '' }}">0.00</strong>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-info">
                                            <td colspan="6" class="text-end">
                                                <label for="totalCommissionInput" class="form-label mb-0">
                                                    <strong>{{ __('Total Commission to Apportion:') }}</strong>
                                                </label>
                                            </td>
                                            <td colspan="2">
                                                <input type="number" step="0.01" 
                                                    class="form-control form-control-sm text-end" 
                                                    id="totalCommissionInput" 
                                                    placeholder="0.00">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-primary" id="apportionCommissionBtn">
                                                    <i class="fa fa-calculator"></i> {{ __('Apportion') }}
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="8" class="text-end">
                                                <strong>{{ __('Commission Total:') }}</strong>
                                            </td>
                                            <td class="text-end">
                                                <strong id="commissionTotal" data-currency="{{ $account->config->currency->iso_code ?? '' }}">0.00</strong>
                                            </td>
                                        </tr>
                                        <tr class="table-secondary">
                                            <td colspan="8" class="text-end">
                                                <strong>{{ __('Total:') }}</strong>
                                            </td>
                                            <td class="text-end">
                                                <strong id="grandTotal" data-currency="{{ $account->config->currency->iso_code ?? '' }}">0.00</strong>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> {{ __('Create Transactions') }}
                                </button>
                                <a href="{{ route('account.history', $account) }}" class="btn btn-secondary">
                                    <i class="fa fa-times"></i> {{ __('Cancel') }}
                                </a>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

<script>
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('batchEntryForm');
        if (!form) {
            console.error('Batch entry form not found');
            return;
        }

        const rows = document.querySelectorAll('.investment-row');
        console.log('Found rows:', rows.length);
        
        function calculateSubtotal(row) {
            const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const commission = parseFloat(row.querySelector('.commission-input').value) || 0;
            const tax = parseFloat(row.querySelector('.tax-input').value) || 0;
            
            const subtotal = (Math.abs(quantity) * price) + commission + tax;
            return subtotal;
        }

        function updateSubtotals() {
            let grandTotal = 0;
            let commissionTotal = 0;
            
            rows.forEach(row => {
                const subtotal = calculateSubtotal(row);
                const transactionType = row.querySelector('.transaction-type').value;
                const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const commission = parseFloat(row.querySelector('.commission-input').value) || 0;
                
                // Display subtotal
                const subtotalElement = row.querySelector('.subtotal');
                if (!subtotalElement) return;
                
                const currencyCode = subtotalElement.getAttribute('data-currency') || '';
                subtotalElement.textContent = currencyCode + ' ' + subtotal.toFixed(2);
                
                // Add to grand total (negative for buys, positive for sells)
                if (quantity !== 0) {
                    commissionTotal += commission;
                    
                    if (transactionType === 'buy') {
                        grandTotal -= subtotal;
                    } else {
                        grandTotal += subtotal;
                    }
                }
            });
            
            // Update commission total
            const commissionTotalElement = document.getElementById('commissionTotal');
            if (commissionTotalElement) {
                const commissionCurrencyCode = commissionTotalElement.getAttribute('data-currency') || '';
                commissionTotalElement.textContent = commissionCurrencyCode + ' ' + commissionTotal.toFixed(2);
            }
            
            // Update grand total
            const grandTotalElement = document.getElementById('grandTotal');
            if (grandTotalElement) {
                const currencyCode = grandTotalElement.getAttribute('data-currency') || '';
                grandTotalElement.textContent = currencyCode + ' ' + grandTotal.toFixed(2);
                
                // Color code the grand total
                if (grandTotal < 0) {
                    grandTotalElement.classList.add('text-danger');
                    grandTotalElement.classList.remove('text-success');
                } else if (grandTotal > 0) {
                    grandTotalElement.classList.add('text-success');
                    grandTotalElement.classList.remove('text-danger');
                } else {
                    grandTotalElement.classList.remove('text-danger', 'text-success');
                }
            }
        }
        
        // Apportion commission across all investments proportionally
        function apportionCommission() {
            const totalCommissionInput = document.getElementById('totalCommissionInput');
            const totalCommission = parseFloat(totalCommissionInput.value) || 0;
            
            if (totalCommission <= 0) {
                alert('Please enter a valid commission amount to apportion.');
                return;
            }
            
            // Calculate the total transaction value (qty * price) for all rows with quantity > 0
            let totalTransactionValue = 0;
            const rowValues = [];
            
            rows.forEach(row => {
                const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const transactionValue = Math.abs(quantity) * price;
                
                rowValues.push({
                    row: row,
                    value: transactionValue,
                    hasQuantity: quantity !== 0
                });
                
                if (quantity !== 0) {
                    totalTransactionValue += transactionValue;
                }
            });
            
            if (totalTransactionValue === 0) {
                alert('Please enter quantities and prices before apportioning commission.');
                return;
            }
            
            // Distribute commission proportionally
            rowValues.forEach(item => {
                if (item.hasQuantity && item.value > 0) {
                    const proportion = item.value / totalTransactionValue;
                    const apportionedCommission = (totalCommission * proportion).toFixed(2);
                    item.row.querySelector('.commission-input').value = apportionedCommission;
                }
            });
            
            // Update all calculations
            updateSubtotals();
            
            // Clear the total commission input
            totalCommissionInput.value = '';
        }
        
        // Attach event listeners
        rows.forEach(row => {
            const inputs = row.querySelectorAll('.quantity-input, .price-input, .commission-input, .tax-input, .transaction-type');
            inputs.forEach(input => {
                input.addEventListener('input', updateSubtotals);
                input.addEventListener('change', updateSubtotals);
            });
        });
        
        // Apportion commission button
        const apportionBtn = document.getElementById('apportionCommissionBtn');
        if (apportionBtn) {
            apportionBtn.addEventListener('click', apportionCommission);
        }
        
        // Initial calculation
        updateSubtotals();
    });
})();
</script>
@endsection
