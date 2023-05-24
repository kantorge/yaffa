@extends('template.layouts.page')

@section('title_postfix', __('Transaction'))

@section('content_container_classes', 'container-fluid')

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
    {{-- display the form based on the type of the transaction --}}
    @if ($type === 'standard')
        <div id="app">
            <transaction-container-standard
                action = "{{ $action }}"
                @if($transaction)
                    :transaction = "{{ $transaction }}"
                @endif
            ></transaction-container-standard>
        </div>
    @elseif ($type === 'investment')
        <div id="app">
            <transaction-container-investment
                action = "{{ $action }}"
                @if($transaction)
                    :transaction = "{{ $transaction }}"
                @endif
            ></transaction-container-investment>
        </div>
    @endif
@endsection
