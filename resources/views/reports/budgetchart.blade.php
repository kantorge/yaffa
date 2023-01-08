@extends('template.layouts.page')

@section('title_postfix', __('Budget chart'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Budget chart'))

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <div class="text-end">
                    @if(!$byYears)
                        <button type="button" class="btn btn-sm btn-primary" title="{{ __('Zoom in') }}" id="btnZoomIn">
                            <i class="fa fa-search"></i>
                        </button>
                    @endif

                    <a
                        class="btn btn-sm {{($byYears ? 'btn-primary' : 'btn-info') }}"
                        href="{{ route('reports.budgetchart', ['byYears' => ($byYears ? '' : 'byYears')]) }}"
                        title="{{($byYears ? __('Switch to monthly view') : __('Switch to yearly view') ) }}">
                        <i class="fa fa-calendar"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div id="chartdiv" style="width:100%;height:500px;"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="row">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="card-title">
                            {{ __('Select categories to display') }}
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="category_tree"></div>
                    </div>
                    <div class="card-footer">
                        <div class="text-end">
                            <button name="reload" type="button" id="clear" class="btn btn-default btn-sm">{{ __('Clear selection') }}</button>
                            <button name="reload" type="button" id="all" class="btn btn-default btn-sm">{{ __('Select all') }}</button>
                            <button name="reload" type="button" id="reload" class="btn btn-primary">{{ __('Load data') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title">
                    {{ __('Scheduled and budgeted transactions for selected categories') }}
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-hover no-footer" id="table"></table>
            </div>
        </div>
    </div>
</div>

@include('template.components.model-delete-form')
@include('template.components.transaction-skip-form')

@stop
