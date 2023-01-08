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
    <div id="app">
        <transaction-container-standard
            action = "{{ $action }}"
            :transaction = "{{ $transaction ?? '{}' }}"
        ></transaction-container-standard>
    </div>
@endsection
