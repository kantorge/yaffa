@extends('template.layouts.page')

@section('title_postfix', __('Missing recharge tags'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Missing recharge tags'))

@section('content')
    @if ($backstopDate)
        <div class="alert alert-info py-2" dusk="recharges-backstop-note">
            <i class="fa fa-filter"></i>
            {{ __('Activity before the backstop date (:date) is hidden.', ['date' => $backstopDate->format('Y-m-d')]) }}
            <a href="{{ route('user.settings') }}">{{ __('Change') }}</a>
        </div>
    @endif
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="card-title">
                {{ __('Items in a category that expects a tag, but have no tag') }}
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.recharges.open') }}">
                {{ __('Open recharge tags') }}
            </a>
        </div>
        @if (! $hasTagRequiredCategories)
            <div class="card-body" dusk="recharges-no-categories">
                {{ __('No category requires a tag yet. Set the tag requirement on a category to track recharges.') }}
                <a href="{{ route('categories.index') }}">{{ __('Categories') }}</a>
            </div>
        @elseif ($items->isEmpty())
            <div class="card-body text-success" dusk="recharges-untagged-empty">
                <i class="fa fa-check"></i>
                {{ __('Every item in a category that expects a tag has one.') }}
            </div>
        @else
            <div class="card-body table-responsive">
                <table class="table table-striped table-bordered table-hover" dusk="table-recharges-untagged">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Account') }}</th>
                            <th>{{ __('Payee') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th>{{ __('Comment') }}</th>
                            <th class="text-center">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php
                                $transaction = $item->transaction;
                                $isWithdrawal = $transaction->transaction_type === \App\Enums\TransactionType::WITHDRAWAL;
                                $account = $isWithdrawal ? $transaction->config?->accountFrom : $transaction->config?->accountTo;
                                $payee = $isWithdrawal ? $transaction->config?->accountTo : $transaction->config?->accountFrom;
                                $amount = (string) $item->amount->getAmount();
                            @endphp
                            <tr>
                                <td data-date="{{ $transaction->date->format('Y-m-d') }}">{{ $transaction->date->format('Y-m-d') }}</td>
                                <td>{{ __($transaction->transaction_type->label()) }}</td>
                                <td>{{ $account?->name }}</td>
                                <td>{{ $payee?->name }}</td>
                                <td>
                                    <a href="{{ route('categories.show', $item->category_id) }}">{{ $item->category->full_name }}</a>
                                </td>
                                <td
                                    class="text-end"
                                    data-money-amount="{{ $amount }}"
                                    data-money-currency="{{ json_encode($transaction->transaction_currency) }}"
                                >{{ $amount }}</td>
                                <td>{{ $item->comment }}</td>
                                <td class="text-center">
                                    <a
                                        class="btn btn-sm btn-primary"
                                        href="{{ route('transaction.open', ['transaction' => $transaction->id, 'action' => 'edit']) }}"
                                        title="{{ __('Edit transaction') }}"
                                    >
                                        <i class="fa fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@stop
