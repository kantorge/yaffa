@extends('template.layouts.page')

@section('title_postfix', __('Investment details'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Investment details') }} - {{ $investment->name }}
@stop

@section('content')
    <div id="app">
        <investment-display-container
            :investment="{{ json_encode($investment) }}"
            :transactions="{{ json_encode($transactions) }}"
            :prices="{{ json_encode($prices) }}"
            :accounts="{{ json_encode($accounts) }}"
            :initial-account="{{ json_encode($selectedAccountId) }}"
        ></investment-display-container>
    </div>
@stop
