@extends('template.layouts.page')

@section('title_postfix',  __('Investment prices'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Investment prices') }} of {{$investment->name}}
@stop

@section('content')
<div id="investmentPriceApp">
    <investment-price-manager :investment="{{ json_encode($investment) }}" :initial-prices="{{ json_encode($prices) }}"></investment-price-manager>
</div>
@stop
