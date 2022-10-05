@extends('template.layouts.page')

@section('title', 'Search')

@section('content_header')
<h1>Search results</h1>
@stop


@section('content')

    {{-- Display the search form --}}
    <form action="{{ route('search') }}" method="get">
        <div class="input-group">
            <input type="text" name="q" value="{{ $searchTerm }}" class="form-control" placeholder="Search..." autocomplete="off">
            <span class="input-group-btn">
                <button type="submit" id="search-btn" class="btn btn-info btn-flat"><i class="fa fa-search"></i>
                </button>
            </span>
        </div>
    </form>

    {{-- Display the search results if any results are set --}}
    @if($results && count($results) > 0)
    <h2>Results for search term: "<em>{{ $searchTerm }}</em>"</h2>

    <div class="row">
        {{-- Accounts --}}
        <div class="col-md-4">
            <div class="box collapsed-box {{ $results['accounts']->count() ? 'box-success' : 'box-default' }}">
                <div class="box-header with-border">
                    <h3 class="box-title">Accounts ({{$results['accounts']->count()}})</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body {{ $results['accounts']->isEmpty() ? '' : 'no-padding' }}">
                    @if(!$results['accounts']->isEmpty())
                        <table class="table" id="accounts">
                            <thead>
                                <tr>
                                    <th>Active</th>
                                    <th>Account</th>
                                    <th>Transactions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results['accounts'] as $account)
                                <tr>
                                    <td class="text-center">
                                        @if($account->active)
                                            <i class="fa fa-check-square text-success" title="Yes"></i>
                                        @else
                                            <i class="fa fa-square text-danger" title="No"></i>
                                        @endif
                                    </td>
                                    <td class="{{ $account->active ? '' : 'text-muted text-italic' }}">{{ $account->name }}</td>
                                    <td class="transactionCount" data-id="{{ $account->id }}" data-type="account"></td>
                                    <td class="accountAction">
                                        <a href="{{ route('transactions.createStandard', ['account_from' => $account->id ]) }}" class="btn btn-xs btn-success" title="New transaction"><i class="fa fa-plus"></i></a>
                                        <a href="{{ route('transactions.createInvestment', ['account' => $account->id ]) }}" class="btn btn-xs btn-success" title="New investment transaction"><i class="fa fa-line-chart"></i></a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        No accounts found
                    @endif
                </div>
            </div>
        </div>

        {{-- Payees --}}
        <div class="col-md-4">
            <div class="box collapsed-box {{ $results['payees']->count() ? 'box-success' : 'box-default' }}">
                <div class="box-header with-border">
                    <h3 class="box-title">Payees ({{$results['payees']->count()}})</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body {{ $results['payees']->isEmpty() ? '' : 'no-padding' }}">
                    @if(!$results['payees']->isEmpty())
                        <table class="table" id="payees">
                            <thead>
                                <tr>
                                    <th>Active</th>
                                    <th>Payee</th>
                                    <th>Transactions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results['payees'] as $payee)
                                <tr>
                                    <td class="text-center">
                                        @if($payee->active)
                                            <i class="fa fa-check-square text-success" title="Yes"></i>
                                        @else
                                            <i class="fa fa-square text-danger" title="No"></i>
                                        @endif
                                    </td>
                                    <td class="{{ $payee->active ? '' : 'text-muted text-italic' }}">{{ $payee->name }}</td>
                                    <td class="transactionCount" data-id="{{ $payee->id }}" data-type="payee"></td>
                                    <td class="payeeAction">
                                        <!-- TODO: planned feature
                                        <a href="{{ route('transactions.createStandard', ['account_to' => $payee->id ]) }}" class="btn btn-xs btn-success" title="New transaction"><i class="fa fa-plus"></i></a>
                                        -->
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        No payees found
                    @endif
                </div>
            </div>
        </div>

        {{-- Investments --}}
        <div class="col-md-4">
            <div class="box collapsed-box {{ $results['investments']->count() ? 'box-success' : 'box-default' }}">
                <div class="box-header with-border">
                    <h3 class="box-title">Investments ({{$results['investments']->count()}})</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    @if(!$results['investments']->isEmpty())
                        <ul class="search-result-list custom-fa-bullet-list" id="investments">
                            @foreach($results['investments'] as $investment)
                            <li class="{{ $investment->active ? 'active' : 'inactive text-muted' }}">
                                {{ $investment->name }}
                                @if(!$investment->active)
                                    (inactive)
                                @endif
                            </li>
                            @endforeach
                        </ul>
                    @else
                        No investments found
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Tags --}}
        <div class="col-md-4">
            <div class="box collapsed-box {{ $results['tags']->count() ? 'box-success' : 'box-default' }}">
                <div class="box-header with-border">
                    <h3 class="box-title">Tags ({{$results['tags']->count()}})</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    @if(!$results['tags']->isEmpty())
                        <ul class="search-result-list custom-fa-bullet-list" id="tags">
                            @foreach($results['tags'] as $tag)
                            <li class="{{ $tag->active ? 'active' : 'inactive text-muted' }}">
                                {{ $tag->name }}
                                @if(!$tag->active)
                                    (inactive)
                                @endif
                                {{-- Placeholder span for transaction count --}}
                                <span class="transactionCount pull-right" data-type="tag" data-id="{{$tag->id}}" class="hidden"></span>
                            </li>
                            @endforeach

                        </ul>
                    @else
                        No tags found
                    @endif
                </div>
            </div>
        </div>
        {{-- Categories --}}
        <div class="col-md-4">
            <div class="box collapsed-box {{ $results['categories']->count() ? 'box-success' : 'box-default' }}">
                <div class="box-header with-border">
                    <h3 class="box-title">Categories ({{$results['categories']->count()}})</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    @if(!$results['categories']->isEmpty())
                        <ul class="search-result-list custom-fa-bullet-list" id="categories">
                            @foreach($results['categories'] as $category)
                            <li class="{{ $category->active ? 'active' : 'inactive text-muted' }}">
                                {{ $category->full_name }}
                                @if(!$category->active)
                                    (inactive)
                                @endif
                                {{-- Placeholder span for transaction count --}}
                                <span class="transactionCount pull-right" data-type="category" data-id="{{$category->id}}" class="hidden"></span>
                            </li>
                            @endforeach
                        </ul>
                    @else
                        No categories found
                    @endif
                </div>
            </div>
        </div>

        {{-- Transactions --}}
        <div class="col-md-4">
            <div class="box collapsed-box {{ $results['transactions']->count() ? 'box-success' : 'box-default' }}">
                <div class="box-header with-border">
                    <h3 class="box-title">Transactions ({{$results['transactions']->count()}})</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body {{ $results['transactions']->isEmpty() ? '' : 'no-padding' }}">
                    @if(!$results['transactions']->isEmpty())
                        <table class="table" id="transactions">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Comment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results['transactions'] as $transaction)
                                    <tr>
                                        <td>{{ $transaction->date->format('Y. m. d.') }}</td>
                                        <td>{{ $transaction->comment }}</td>
                                        <td class="transactionIcon hidden" data-id={{$transaction->id}}></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        No transactions found
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="app">
        <transaction-show-modal></transaction-show-modal>
    </div>

    @endif

@stop
