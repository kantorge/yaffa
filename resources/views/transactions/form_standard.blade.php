@extends('template.page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('classes_body', "layout-footer-fixed")

@section('title', 'Transaction')

@section('content')

    <div id="app">

        <transaction-form-standard
            action = "{{ $action }}"
            callback = "{{ $callback ?? 'newStandard'}}"
            form-url = "{{ $transaction ? route('transactions.updateStandard', ['transaction' => $transaction->id]) : route('transactions.storeStandard') }}"
            :transaction = "{{ $transaction }}"
        ></transaction-form-standard>

    </div>



@endsection
