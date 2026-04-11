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
        ></investment-display-container>
    </div>

    @include('template.components.model-delete-form')
@stop
