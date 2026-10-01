@extends('template.layouts.page')

@section('title_postfix', __('Payee details'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Payee details') }} - {{ $payee->name }}
@stop

@section('content')
    {{-- Data is passed to the Vue island via window.payee, window.overview, window.categorySuggestion, window.baseCurrency --}}
    <div id="payeeShow"></div>
@stop
