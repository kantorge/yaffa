@extends('template.page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('classes_body', "layout-footer-fixed")

@section('title', 'Transaction')

@section('content_header')
    @switch($action)
        @case('create')
            Add new transaction
            @break

        @case('edit')
            Modify existing transaction
            @break

        @case('clone')
            Clone existing transaction
            @break

        @case('enter')
            Enter scheduled transaction instance
            @break
    @endswitch
@endsection

@section('content')
    <div id="app">
        <transaction-form-investment
            action = "{{ $action }}"
            form-url = "{{ $transaction && $transaction->id ? route('transactions.updateInvestment', ['transaction' => $transaction->id]) : route('transactions.storeInvestment') }}"
            :transaction = "{{ $transaction }}"
        ></transaction-form-investment>
    </div>
@endsection
