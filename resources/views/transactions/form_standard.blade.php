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
        <transaction-form-standard
            action = "{{ $action }}"
            form-url = "{{ $transaction && $transaction->id ? route('transactions.updateStandard', ['transaction' => $transaction->id]) : route('transactions.storeStandard') }}"
            :transaction = "{{ $transaction ?? '{}' }}"
        ></transaction-form-standard>
    </div>
@endsection
