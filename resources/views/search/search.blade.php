@extends('template.layouts.page')

@section('title_postfix', __('Search'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Search results'))

@section('content')
    {{-- Display the search form --}}
    <form action="{{ route('search') }}" method="get">
        <div class="input-group">
            <input type="text" name="q" value="{{ $searchTerm }}" class="form-control"
                   placeholder="{{ __('Search...') }}" autocomplete="off">
            <button type="submit" id="search-btn" class="btn btn-info btn-flat"><i class="fa fa-search"></i></button>
        </div>
    </form>

    {{-- Display the search results if any results are set --}}
    @if($results && count($results) > 0)
        <h2 class="mt-3 mb-3">
            {{ __('Results for search term:') }} "<em>{{ $searchTerm }}</em>"
        </h2>

        <div class="row">
            {{-- Accounts --}}
            <div class="col-md-4">
                <div class="card border-top-3 mb-3 {{ $results['accounts']->count() ? 'border-top-success' : 'border-top-default' }}">
                    <div class="card-header">
                        <div class="card-title collapse-control">
                            <span class="collapsed" data-coreui-toggle="collapse" href="#collapse-search-results-accounts"
                                  aria-expanded="true" aria-controls="collapse-search-results-accounts">
                                <i class="fa fa-angle-down"></i>
                                {{ __('Accounts') }} ({{$results['accounts']->count()}})
                            </span>
                        </div>
                    </div>
                    @if(!$results['accounts']->isEmpty())
                        <div class="card-body collapse" id="collapse-search-results-accounts">
                            <table class="table" id="table-search-results-accounts">
                                <thead>
                                    <tr>
                                        <th>{{ __('Active') }}</th>
                                        <th>{{ __('Account') }}</th>
                                        <th>{{ __('Transactions') }}</th>
                                        <th>{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($results['accounts'] as $account)
                                    <tr>
                                        <td class="text-center">
                                            @if($account->active)
                                                <i class="fa fa-check-square text-success" title="{{ __('Yes') }}"></i>
                                            @else
                                                <i class="fa fa-square text-danger" title="{{ __('No') }}"></i>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('account-entity.show', $account->id) }}">
                                                {{ $account->name }}
                                            </a>
                                        </td>
                                        <td class="transactionCount"
                                            data-id="{{ $account->id }}"
                                            data-type="account"></td>
                                        <td class="accountAction">
                                            <a class="btn btn-sm btn-success"
                                               href="{{ route('transaction.create', ['type' => 'standard', 'account_from' => $account->id ]) }}"
                                               title="{{ __('New transaction') }}"
                                            >
                                                <i class="fa fa-plus"></i>
                                            </a>
                                            <a class="btn btn-sm btn-success"
                                               href="{{ route('transaction.create', ['type' => 'investment', 'account' => $account->id ]) }}"
                                               title="{{ __('New investment transaction') }}"
                                            >
                                                <i class="fa fa-line-chart"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Payees --}}
            <div class="col-md-4">
                <div class="card border-top-3 mb-3 {{ $results['payees']->count() ? 'border-top-success' : 'border-top-default' }}">
                    <div class="card-header">
                        <div class="card-title collapse-control">
                            <span class="collapsed" data-coreui-toggle="collapse" href="#collapse-search-results-payees"
                                  aria-expanded="true" aria-controls="collapse-search-results-payees">
                                <i class="fa fa-angle-down"></i>
                                {{ __('Payees') }} ({{$results['payees']->count()}})
                            </span>
                        </div>
                    </div>
                    @if(!$results['payees']->isEmpty())
                        <div class="card-body collapse" id="collapse-search-results-payees">
                            <table class="table" id="table-search-results-payees">
                                <thead>
                                <tr>
                                    <th>{{ __('Active') }}</th>
                                    <th>{{ __('Payee') }}</th>
                                    <th>{{ __('Transactions') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($results['payees'] as $payee)
                                    <tr>
                                        <td class="text-center">
                                            @if($payee->active)
                                                <i class="fa fa-check-square text-success" title="{{ __('Yes') }}"></i>
                                            @else
                                                <i class="fa fa-square text-danger" title="{{ __('No') }}"></i>
                                            @endif
                                        </td>
                                        <td class="{{ $payee->active ? '' : 'text-muted text-italic' }}">{{ $payee->name }}</td>
                                        <td class="transactionCount" data-id="{{ $payee->id }}" data-type="payee"></td>
                                        <td class="payeeAction">
                                            {{-- TODO: planned feature
                                            <a href="{{ route('transaction.create', ['type' => 'standard', 'account_to' => $payee->id ]) }}" class="btn btn-sm btn-success" title="New transaction"><i class="fa fa-plus"></i></a>
                                            --}}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Investments --}}
            <div class="col-md-4">
                <div class="card border-top-3 mb-3 {{ $results['investments']->count() ? 'border-top-success' : 'border-top-default' }}">
                    <div class="card-header">
                        <div class="card-title collapse-control">
                            <span class="collapsed" data-coreui-toggle="collapse"
                                  href="#collapse-search-results-investments" aria-expanded="true"
                                  aria-controls="collapse-search-results-investments">
                                <i class="fa fa-angle-down"></i>
                                {{ __('Investments') }} ({{$results['investments']->count()}})
                            </span>
                        </div>
                    </div>
                    @if(!$results['investments']->isEmpty())
                        <div class="card-body collapse" id="collapse-search-results-investments">
                            <ul class="search-result-list custom-fa-bullet-list">
                                @foreach($results['investments'] as $investment)
                                    <li class="{{ $investment->active ? 'active' : 'inactive text-muted' }}">
                                        {{ $investment->name }}
                                        @if(!$investment->active)
                                            ({{ __('inactive') }})
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    <div class="row">
        {{-- Tags --}}
        <div class="col-md-4">
            <div class="card border-top-3 mb-3 {{ $results['tags']->count() ? 'border-top-success' : 'border-top-default' }}">
                <div class="card-header">
                    <div class="card-title collapse-control">
                        <span class="collapsed" data-coreui-toggle="collapse" href="#collapse-search-results-tags" aria-expanded="true" aria-controls="collapse-search-results-tags">
                            <i class="fa fa-angle-down"></i>
                            {{ __('Tags') }} ({{$results['tags']->count()}})
                        </span>
                        </div>
                    </div>
                    @if(!$results['tags']->isEmpty())
                        <div class="card-body collapse" id="collapse-search-results-tags">
                            <ul class="search-result-list custom-fa-bullet-list" id="list-search-results-tags">
                                @foreach($results['tags'] as $tag)
                                    <li class="{{ $tag->active ? 'active' : 'inactive text-muted' }}">
                                        {{ $tag->name }}
                                        @if(!$tag->active)
                                            ({{ __('inactive') }})
                                        @endif
                                        {{-- Placeholder span for transaction count --}}
                                        <span class="transactionCount pull-right hidden" data-type="tag"
                                              data-id="{{$tag->id}}"></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Categories --}}
            <div class="col-md-4">
                <div class="card border-top-3 mb-3 {{ $results['categories']->count() ? 'border-top-success' : 'border-top-default' }}">
                    <div class="card-header">
                        <div class="card-title collapse-control">
                            <span class="collapsed" data-coreui-toggle="collapse" href="#collapse-search-results-categories"
                                  aria-expanded="true" aria-controls="collapse-search-results-categories">
                                <i class="fa fa-angle-down"></i>
                                {{ __('Categories') }} ({{$results['categories']->count()}})
                            </span>
                        </div>
                    </div>
                    @if(!$results['categories']->isEmpty())
                        <div class="card-body collapse" id="collapse-search-results-categories">
                            <ul class="search-result-list custom-fa-bullet-list" id="list-search-results-categories">
                                @foreach($results['categories'] as $category)
                                    <li class="{{ $category->active ? 'active' : 'inactive text-muted' }}">
                                        {{ $category->full_name }}
                                        @if(!$category->active)
                                            ({{ __('inactive') }})
                                        @endif
                                        {{-- Placeholder span for transaction count --}}
                                        <span class="transactionCount pull-right hidden" data-type="category"
                                              data-id="{{$category->id}}"></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Transactions --}}
            <div class="col-md-4">
                <div class="card border-top-3 mb-3 {{ $results['transactions']->count() ? 'border-top-success' : 'border-top-default' }}">
                    <div class="card-header">
                        <div class="card-title collapse-control">
                            <span class="collapsed" data-coreui-toggle="collapse"
                                  href="#collapse-search-results-transactions" aria-expanded="true"
                                  aria-controls="collapse-search-results-transactions">
                                <i class="fa fa-angle-down"></i>
                                {{ __('Transactions') }} ({{$results['transactions']->count()}})
                            </span>
                        </div>
                    </div>
                    @if(!$results['transactions']->isEmpty())
                        <div class="card-body collapse" id="collapse-search-results-transactions">
                            <table class="table" id="table-search-results-transactions">
                                <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Comment') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($results['transactions'] as $transaction)
                                    <tr>
                                        <td>
                                            @if(!$transaction->schedule && !$transaction->budget)
                                                {{ $transaction->date->format('Y. m. d.') }}
                                            @else
                                                <i class="fa-solid fa-calendar-days text-info"></i>
                                             @endif
                                        </td>
                                        <td>{{ $transaction->comment }}</td>
                                        <td class="transactionIcon hidden" data-id={{$transaction->id}}></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div id="app">
            <transaction-show-modal></transaction-show-modal>
        </div>
    @endif
@stop
