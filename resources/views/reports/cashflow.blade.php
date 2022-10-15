@extends('template.layouts.page')

@section('title', __('Cash flow'))

@section('content_header', __('Cash flow'))

@section('content')

<div class="row">
    <div class="col-sm-12">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">
                    {{ __('Cash flow') }}
                </h3>
                <div class="pull-right box-tools">
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
                <!-- /.box-tools -->
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <div id="chartdiv" style="width:100%;height:500px;"></div>
            </div>
        </div>
    </div>
</div>

@stop
