@extends('template.layouts.page')

@section('title_postfix', __('Open recharge tags'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Open recharge tags'))

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
                {{ __('Tags in an "expect nil" category that are not settled') }}
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.recharges.untagged') }}">
                {{ __('Missing recharge tags') }}
            </a>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                {{ __('The balance counts every item with the tag, in any category: deposits add, withdrawals subtract. Amounts are converted to the base currency. A tag within :tolerance of zero is settled.', ['tolerance' => $tolerance]) }}
            </p>
            @if ($openTags->isEmpty())
                <div class="text-success" dusk="recharges-open-empty">
                    <i class="fa fa-check"></i>
                    {{ __('No open recharge tags.') }}
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover" dusk="table-recharges-open">
                        <thead>
                            <tr>
                                <th>{{ __('Tag') }}</th>
                                <th class="text-end">{{ __('Balance') }}</th>
                                <th class="text-center">{{ __('Items') }}</th>
                                <th>{{ __('First date') }}</th>
                                <th>{{ __('Last date') }}</th>
                                <th class="text-center">{{ __('Active') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($openTags as $tag)
                                <tr>
                                    <td>
                                        <a href="{{ route('reports.transactions', ['tags' => [$tag['id']]]) }}">{{ $tag['name'] }}</a>
                                    </td>
                                    <td class="text-end">
                                        <span
                                            class="{{ str_starts_with($tag['balance'], '-') ? 'text-danger' : 'text-success' }}"
                                            data-money-amount="{{ $tag['balance'] }}"
                                            data-money-currency="{{ json_encode($baseCurrency) }}"
                                        >{{ $tag['balance'] }}</span>
                                        @if ($tag['rates_missing'])
                                            <i
                                                class="fa fa-triangle-exclamation text-warning ms-1"
                                                title="{{ __('Some amounts could not be converted, because a currency rate is missing.') }}"
                                            ></i>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $tag['item_count'] }}</td>
                                    <td data-date="{{ $tag['first_date'] }}">{{ $tag['first_date'] }}</td>
                                    <td data-date="{{ $tag['last_date'] }}">{{ $tag['last_date'] }}</td>
                                    <td class="text-center">
                                        <i class="fa {{ $tag['active'] ? 'fa-check text-success' : 'fa-xmark text-danger' }}"></i>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="card-title">
                {{ __('Settled tags that are still active') }}
            </div>
            @if ($settledTags->isNotEmpty())
                <form method="POST" action="{{ route('reports.recharges.deactivate-settled') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-warning" dusk="button-deactivate-all-settled">
                        <i class="fa fa-power-off"></i>
                        {{ __('Deactivate all settled') }}
                    </button>
                </form>
            @endif
        </div>
        <div class="card-body">
            @if ($settledTags->isEmpty())
                <div class="text-muted" dusk="recharges-settled-empty">
                    {{ __('No settled tags waiting to be deactivated.') }}
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover" dusk="table-recharges-settled">
                        <thead>
                            <tr>
                                <th>{{ __('Tag') }}</th>
                                <th class="text-center">{{ __('Items') }}</th>
                                <th>{{ __('Last date') }}</th>
                                <th class="text-center">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($settledTags as $tag)
                                <tr>
                                    <td>
                                        <a href="{{ route('reports.transactions', ['tags' => [$tag['id']]]) }}">{{ $tag['name'] }}</a>
                                    </td>
                                    <td class="text-center">{{ $tag['item_count'] }}</td>
                                    <td data-date="{{ $tag['last_date'] }}">{{ $tag['last_date'] }}</td>
                                    <td class="text-center">
                                        <form method="POST" action="{{ route('reports.recharges.deactivate-settled') }}">
                                            @csrf
                                            <input type="hidden" name="tag_ids[]" value="{{ $tag['id'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                <i class="fa fa-power-off"></i>
                                                {{ __('Deactivate') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@stop
