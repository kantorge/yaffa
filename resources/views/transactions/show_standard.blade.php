@extends('template.layouts.page')

@section('classes_body')
    @parent
@endsection

@section('title', 'Transaction')

@section('content_header')
    Transaction details
@endsection

@section('content')
    <div id="app">
        <transaction-show-standard
            :transaction = "{{ $transaction ?? '{}' }}"
        ></transaction-show-standard>
    </div>
@endsection
