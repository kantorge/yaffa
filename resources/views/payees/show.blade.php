@extends('template.layouts.page')

@section('title_postfix', __('Payee details'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Payee details') }} - {{ $payee->name }}
@stop

@section('content')
    {{-- Vue page island is added in the frontend step; data is available as window.payee, window.overview --}}
    <div id="payeeShow"></div>
@stop
