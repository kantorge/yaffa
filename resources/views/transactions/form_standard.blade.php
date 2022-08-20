@extends('template.layouts.page')

@section('classes_body')
    @parent
    layout-footer-fixed
@endsection

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

        @case('replace')
            Clone scheduled transaction and close base item
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
