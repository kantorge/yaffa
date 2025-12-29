@extends('template.layouts.page')

@section('title_postfix', __('Batch Reconcile - Investment Account'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Batch Reconcile - Investment Account') }}
    <small class="text-muted">{{ $account->name }}</small>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <strong>{{ __('Reconcile investments in :account', ['account' => $account->name]) }}</strong>
                    <div class="float-end">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Sort options">
                            <input type="radio" class="btn-check" name="sortOption" id="sortAlpha" value="alpha" checked>
                            <label class="btn btn-outline-primary" for="sortAlpha" title="{{ __('Sort alphabetically') }}">
                                <i class="fa fa-sort-alpha-asc"></i> A-Z
                            </label>
                            <input type="radio" class="btn-check" name="sortOption" id="sortValue" value="value">
                            <label class="btn btn-outline-primary" for="sortValue" title="{{ __('Sort by value') }}">
                                <i class="fa fa-sort-amount-desc"></i> {{ __('Value') }}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if($investments->isEmpty())
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i>
                            {{ __('No investments found in this account.') }}
                        </div>
                        <a href="{{ route('transaction.create', ['type' => 'investment']) }}" class="btn btn-primary">
                            <i class="fa fa-plus"></i> {{ __('Add Investment Transaction') }}
                        </a>
                    @else
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i>
                            <strong>{{ __('How it works:') }}</strong>
                            <ul class="mb-0">
                                <li>{{ __('Enter the reconciliation date and your statement quantities') }}</li>
                                <li>{{ __('If quantities match, Buy/Sell transactions will be marked as reconciled') }}</li>
                                <li>{{ __('If quantities differ, an adjustment transaction will be created with comment "RECONCILE ERROR - TO CHECK"') }}</li>
                                <li>{{ __('Optionally enter prices to update price history and see valuations') }}</li>
                            </ul>
                        </div>

                        <form action="{{ route('account.batch-reconcile.investment.store', $account) }}" method="POST" id="batchReconcileForm">
                            @csrf
                            
                            <div class="mb-3">
                                <label for="date" class="form-label">{{ __('Reconciliation Date') }}</label>
                                <input type="date" class="form-control" id="date" name="date" 
                                    value="{{ old('date', now()->format('Y-m-d')) }}" required>
                                <div class="form-text">{{ __('Statement date for reconciliation') }}</div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Investment') }}</th>
                                            <th class="text-end">{{ __('System Qty') }}</th>
                                            <th class="text-end">{{ __('Statement Qty') }}</th>
                                            <th class="text-end">{{ __('Difference') }}</th>
                                            <th class="text-end">{{ __('Price') }}</th>
                                            <th class="text-end">{{ __('Value') }}</th>
                                            <th class="text-center">{{ __('Status') }}</th>
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
                                                    <span class="current-qty text-muted">{{ number_format($investment->current_quantity, 4) }}</span>
                                                    <input type="hidden" name="reconciliations[{{ $loop->index }}][current_quantity]" 
                                                        value="{{ $investment->current_quantity }}" 
                                                        class="current-qty-input">
                                                    <input type="hidden" name="reconciliations[{{ $loop->index }}][investment_id]" 
                                                        value="{{ $investment->id }}">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" 
                                                        class="form-control form-control-sm text-end statement-qty-input" 
                                                        name="reconciliations[{{ $loop->index }}][statement_quantity]" 
                                                        value="{{ $investment->current_quantity }}" 
                                                        placeholder="0.0000" required>
                                                </td>
                                                <td class="text-end">
                                                    <span class="difference-display badge"></span>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" 
                                                        class="form-control form-control-sm text-end price-input" 
                                                        name="reconciliations[{{ $loop->index }}][price]" 
                                                        value="{{ $investment->latest_price ?? '' }}" 
                                                        placeholder="Optional">
                                                </td>
                                                <td class="text-end">
                                                    <strong class="value-display">-</strong>
                                                </td>
                                                <td class="text-center">
                                                    <span class="status-badge badge bg-secondary">{{ __('Pending') }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-secondary">
                                            <td colspan="5" class="text-end">
                                                <strong>{{ __('Total Value:') }}</strong>
                                            </td>
                                            <td class="text-end">
                                                <strong id="totalValue">-</strong>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-check"></i> {{ __('Reconcile') }}
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
        const form = document.getElementById('batchReconcileForm');
        if (!form) return;

        const rows = document.querySelectorAll('.investment-row');
        const currency = '{{ $account->config->currency->iso_code ?? "GBP" }}';
        const dateInput = document.getElementById('date');
        const accountId = {{ $account->id }};
        const tbody = document.querySelector('tbody');
        
        // Store original row order
        const rowsArray = Array.from(rows);
        
        // Collect investment IDs
        const investmentIds = rowsArray.map(row => 
            parseInt(row.getAttribute('data-investment-id'))
        );
        
        // Sorting function
        function sortRows(sortBy) {
            const sortedRows = [...rowsArray];
            
            if (sortBy === 'alpha') {
                // Sort alphabetically by investment name
                sortedRows.sort((a, b) => {
                    const nameA = a.querySelector('strong').textContent.trim().toLowerCase();
                    const nameB = b.querySelector('strong').textContent.trim().toLowerCase();
                    return nameA.localeCompare(nameB);
                });
            } else if (sortBy === 'value') {
                // Sort by value (descending)
                sortedRows.sort((a, b) => {
                    const valueA = calculateRowValue(a);
                    const valueB = calculateRowValue(b);
                    return valueB - valueA; // Descending order
                });
            }
            
            // Re-append rows in new order
            sortedRows.forEach(row => tbody.appendChild(row));
            
            // Update all calculations after reordering
            updateAll();
        }
        
        // Helper to calculate row value
        function calculateRowValue(row) {
            const qty = parseFloat(row.querySelector('.statement-qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            return qty * price;
        }
        
        // Add sort toggle listeners
        document.getElementById('sortAlpha').addEventListener('change', function() {
            if (this.checked) sortRows('alpha');
        });
        document.getElementById('sortValue').addEventListener('change', function() {
            if (this.checked) sortRows('value');
        });
        
        function updateRow(row) {
            const currentQty = parseFloat(row.querySelector('.current-qty-input').value) || 0;
            const statementQty = parseFloat(row.querySelector('.statement-qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            
            const difference = statementQty - currentQty;
            const differenceDisplay = row.querySelector('.difference-display');
            const statusBadge = row.querySelector('.status-badge');
            const valueDisplay = row.querySelector('.value-display');
            const currentQtyDisplay = row.querySelector('.current-qty');
            
            // Update current qty display
            currentQtyDisplay.textContent = currentQty.toFixed(4);
            
            // Update difference display
            if (Math.abs(difference) < 0.0001) {
                differenceDisplay.textContent = '✓ Match';
                differenceDisplay.className = 'difference-display badge bg-success';
                statusBadge.textContent = '{{ __("Will Reconcile") }}';
                statusBadge.className = 'status-badge badge bg-success';
            } else {
                differenceDisplay.textContent = (difference > 0 ? '+' : '') + difference.toFixed(4);
                differenceDisplay.className = 'difference-display badge bg-warning';
                statusBadge.textContent = '{{ __("Will Adjust") }}';
                statusBadge.className = 'status-badge badge bg-warning';
            }
            
            // Update value display
            if (price > 0 && statementQty > 0) {
                const value = price * statementQty;
                valueDisplay.textContent = currency + ' ' + value.toFixed(2);
            } else {
                valueDisplay.textContent = '-';
            }
            
            return price > 0 && statementQty > 0 ? (price * statementQty) : 0;
        }

        function updateAll() {
            let totalValue = 0;
            
            rowsArray.forEach(row => {
                totalValue += updateRow(row);
            });
            
            const totalValueElement = document.getElementById('totalValue');
            if (totalValue > 0) {
                totalValueElement.textContent = currency + ' ' + totalValue.toFixed(2);
            } else {
                totalValueElement.textContent = '-';
            }
        }
        
        // Fetch quantities as of selected date
        async function fetchQuantitiesForDate(date) {
            try {
                const response = await fetch('{{ route("account.batch-reconcile.investment.quantities", $account) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        date: date,
                        investment_ids: investmentIds
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update each row with the fetched quantity
                    rowsArray.forEach(row => {
                        const investmentId = parseInt(row.getAttribute('data-investment-id'));
                        const quantity = data.quantities[investmentId] || 0;
                        row.querySelector('.current-qty-input').value = quantity;
                    });
                    
                    updateAll();
                }
            } catch (error) {
                console.error('Error fetching quantities:', error);
            }
        }

        // Add event listeners
        rowsArray.forEach(row => {
            row.querySelector('.statement-qty-input').addEventListener('input', updateAll);
            row.querySelector('.price-input').addEventListener('input', updateAll);
        });
        
        // Fetch quantities when date changes
        dateInput.addEventListener('change', function() {
            if (this.value) {
                fetchQuantitiesForDate(this.value);
            }
        });

        // Initial calculation
        updateAll();
    });
})();
</script>
@endsection
