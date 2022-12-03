@extends('template.layouts.page')

@section('title_postfix', __('Cash flow'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Cash flow'))

@section('content')
<div class="card">
    <div class="card-header">
        <div class="text-end">
            <a
                class="btn {{($singleAxes ? 'btn-primary' : 'btn-info') }}"
                href="{{ route('reports.cashflow', ['withForecast' => ($withForecast ? 'withForecast' : ''), 'singleAxes' => ($singleAxes ? '' : 'singleAxes')]) }}"
                title="{{($singleAxes ? __('Show on two axes') : __('Show on same axes')) }}">
                <i class="fa fa-lock"></i>
            </a>
            <a
                class="btn {{($withForecast ? 'btn-primary' : 'btn-info') }}"
                href="{{ route('reports.cashflow', ['withForecast' => ($withForecast ? '' : 'withForecast'), 'singleAxes' => ($singleAxes ? 'singleAxes' : '')]) }}"
                title="{{($withForecast ? __('Without forecast') : __('With forecast')) }}">
                <i class="fa fa-calendar"></i>
            </a>
        </div>
    </div>
    <div class="card-body">
        <div id="chartdiv" style="width:100%;height:500px;"></div>
    </div>
</div>
@stop
