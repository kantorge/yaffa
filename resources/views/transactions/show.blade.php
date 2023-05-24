@extends('template.layouts.page')

@section('title_postfix', __('Transaction details'))

@section('content_header')
    {{ __('Transaction details') }}
@endsection

@section('content')
    <div id="app">
        <transaction-show-container></transaction-show-container>
    </div>
@endsection
