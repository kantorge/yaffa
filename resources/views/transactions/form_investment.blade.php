@extends('template.layouts.page')

@section('classes_body')
    @parent
    layout-footer-fixed
@endsection

@section('title', __('Transaction'))

@section('content_header')
    @switch($action)
        @case('create')
            {{ __('Add new transaction') }}
            @break

        @case('edit')
            {{ __('Modify existing transaction') }}
            @break

        @case('clone')
            {{ __('Clone existing transaction') }}
            @break

        @case('enter')
            {{ __('Enter scheduled transaction instance') }}
            @break

        @case('replace')
            {{ __('Clone scheduled transaction and close base item') }}
            @break

    @endswitch
@endsection

@section('content')
    <div id="app">
        <transaction-form-investment
            action = "{{ $action }}"
            :transaction = "{{ $transaction ?? '{}' }}"
        ></transaction-form-investment>
    </div>
@endsection
