@extends('template.layouts.page')

@section('title', __('Currency rates'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
{{ __('Currency rates') . ' - ' . $from->iso_code . ' → ' . $to->iso_code }}
@stop

@section('content')
<div id="currencyRateApp">
    <currency-rate-manager :from="{{ json_encode($from) }}" :to="{{ json_encode($to) }}"
        :initial-rates="{{ json_encode($currencyRates) }}"></currency-rate-manager>
</div>
@stop