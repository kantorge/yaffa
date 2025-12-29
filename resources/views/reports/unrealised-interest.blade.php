@extends('template.layouts.page')

@section('title_postfix', __('Unrealised Interest Report'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Unrealised Interest Report'))

@section('content')

<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0">{{ __('Filter') }}</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('reports.unrealised_interest') }}" class="row g-3">
            <div class="col-md-6">
                <label for="tax_year" class="form-label">{{ __('Tax Year') }}</label>
                <select name="tax_year" id="tax_year" class="form-select">
                    <option value="">{{ __('Current Tax Year') }}</option>
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" {{ $taxYear === $year ? 'selected' : '' }}>
                            {{ $year }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> {{ __('Filter') }}
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                {{ __('Unrealised Interest') }}
                <small class="text-muted">({{ $label }})</small>
            </h6>
        </div>
    </div>
    <div class="card-body">
        @if($report->isEmpty())
            <p class="text-muted mb-0">
                {{ __('No investments with interest rates found in this period.') }}
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Investment') }}</th>
                            <th>{{ __('Account') }}</th>
                            <th class="text-end">{{ __('Interest Rate') }}</th>
                            <th class="text-end">{{ __('Unrealised Interest') }}</th>
                            <th class="text-end">{{ __('Realised Interest') }}</th>
                            <th class="text-end">{{ __('Total Interest') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalUnrealised = 0;
                            $totalRealised = 0;
                            $totalInterest = 0;
                        @endphp
                        @foreach($report as $item)
                            @php
                                $totalUnrealised += $item['unrealised'];
                                $totalRealised += $item['realised'];
                                $totalInterest += $item['total'];
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $item['investment_name'] }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $item['currency'] }}</small>
                                </td>
                                <td>{{ $item['account_name'] }}</td>
                                <td class="text-end">
                                    <span class="badge bg-secondary">
                                        {{ number_format($item['interest_rate'] * 100, 2) }}%
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success">
                                        {{ number_format($item['unrealised'], 2) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-primary">
                                        {{ number_format($item['realised'], 2) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-info">
                                        {{ number_format($item['total'], 2) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="3" class="text-end">{{ __('TOTAL') }}</td>
                            <td class="text-end">
                                <span class="badge bg-success">
                                    {{ number_format($totalUnrealised, 2) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-primary">
                                    {{ number_format($totalRealised, 2) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-info">
                                    {{ number_format($totalInterest, 2) }}
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fa fa-info-circle"></i> {{ __('About this Report') }}
        </h6>
    </div>
    <div class="card-body small">
        <ul class="mb-0">
            <li>
                <strong>{{ __('Unrealised Interest') }}</strong> - Interest that has accrued on your investments but has not yet been paid.
                Calculated using daily compound interest.
            </li>
            <li>
                <strong>{{ __('Realised Interest') }}</strong> - Interest that has been received and recorded as transactions.
            </li>
            <li>
                <strong>{{ __('Total Interest') }}</strong> - The sum of unrealised and realised interest.
            </li>
            <li>
                Only investments with configured interest rates are included in this report.
            </li>
        </ul>
    </div>
</div>

@endsection
