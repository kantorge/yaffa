@extends('template.layouts.page')

@section('title_postfix', __('Unrealised Interest'))

@section('content_container_classes', 'container-lg')

@section('content_header')
    {{ __('Unrealised Interest') }} - {{ $investment->name }}
@stop

@section('content')

<div class="mb-3">
    <a href="{{ route('investment.show', $investment) }}" class="btn btn-secondary">
        <i class="fa fa-arrow-left"></i> {{ __('Back to Investment') }}
    </a>
</div>

@if(!$interestData['has_rate'])
    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        {{ __('This investment does not have an interest rate configured. Please add an interest rate to calculate unrealised interest.') }}
    </div>
@else

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">{{ __('Interest Rate') }}</div>
                <div class="h4 mb-0">
                    {{ number_format($interestData['interest_rate'] * 100, 2) }}%
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">{{ __('Total Unrealised Interest') }}</div>
                <div class="h4 mb-0 text-success">
                    {{ $investment->currency->symbol }}
                    {{ number_format($interestData['total_unrealised'], 2) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">{{ __('Total Realised Interest') }}</div>
                <div class="h4 mb-0 text-primary">
                    {{ $investment->currency->symbol }}
                    {{ number_format($interestData['total_realised'], 2) }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h6 class="mb-0">
            {{ __('Interest by Account') }}
        </h6>
    </div>
    <div class="card-body">
        @if(empty($interestData['accounts']))
            <p class="text-muted mb-0">
                {{ __('No transactions found for this investment.') }}
            </p>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Account') }}</th>
                            <th class="text-end">{{ __('Unrealised Interest') }}</th>
                            <th class="text-end">{{ __('Realised Interest') }}</th>
                            <th class="text-end">{{ __('Total Interest') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($interestData['accounts'] as $account)
                            <tr>
                                <td>
                                    <strong>{{ $account['account_name'] }}</strong>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success">
                                        {{ $investment->currency->symbol }}
                                        {{ number_format($account['unrealised'], 2) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-primary">
                                        {{ $investment->currency->symbol }}
                                        {{ number_format($account['realised'], 2) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-info">
                                        {{ $investment->currency->symbol }}
                                        {{ number_format($account['unrealised'] + $account['realised'], 2) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fa fa-info-circle"></i> {{ __('About Unrealised Interest') }}
        </h6>
    </div>
    <div class="card-body small">
        <p>
            <strong>{{ __('Unrealised Interest') }}</strong> is interest that has accrued on your investment but has not yet been paid out. 
            It's calculated using daily compound interest formula based on the interest rate and principal amount.
        </p>
        <p>
            <strong>{{ __('Realised Interest') }}</strong> is interest that has already been received and recorded as transactions.
        </p>
        <p class="mb-0">
            <strong>{{ __('Formula') }}:</strong> Interest = Principal × [(1 + r/365)^days - 1]
            <br>where r = annual interest rate and days = number of days elapsed.
        </p>
    </div>
</div>

@endif

@endsection
