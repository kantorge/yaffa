@extends('template.layouts.page')

@section('title_postfix', __('Cash flow'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Cash flow'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <div class="col-6 col-sm-4">
                    <select class="form-select" id="cashflowAccount"></select>
                </div>
                <div></div>
                <div class="text-end">
                    <button type="button" class="btn btn-primary" id="btnReload">{{ __('Reload') }}</button>

                    <input type="checkbox" class="btn-check" id="singleAxis" checked autocomplete="off">
                    <label class="btn btn-outline-primary" for="singleAxis" title="{{ __('Show on same axis') }}"><i class="fa fa-lock"></i></label>

                    <input type="checkbox" class="btn-check" id="withForecast" {{ $withForecast ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-outline-primary" for="withForecast" title="{{ __('With forecast') }}"><i class="fa fa-calendar"></i></label>
                </div>
            </div>
            <div class="card-body">
                <span class="placeholder-glow"><span id="placeholder" class="placeholder col-12 placeholder-lg"></span></span>
                <div id="chartdiv" class="hidden" style="width:100%; height:500px;"></div>
            </div>
        </div>
    </div>
</div>
@stop
