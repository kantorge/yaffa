@extends('template.layouts.page')

@section('title', __('Budget chart'))

@section('content_header', __('Budget chart'))

@section('content')

<div class="row">
    <div class="col-lg-8">
        <div class="box">
            <div class="box-header">
                <div class="pull-right box-tools">
                    @if(!$byYears)
                        <button type="button" class="btn btn-primary" title="Zoom in" id="btnZoomIn">
                            <i class="fa fa-search"></i>
                        </button>
                    @endif

                    <a
                        class="btn {{($byYears ? 'btn-primary' : 'btn-info') }}"
                        href="{{ route('reports.budgetchart', ['byYears' => ($byYears ? '' : 'byYears')]) }}"
                        title="{{($byYears ? __('Switch to monthly view') : __('Switch to yearly view') ) }}">
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
    <div class="col-lg-4">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title">
                            {{ __('Select categories to display') }}
                        </h3>
                    </div>
                    <div class="box-body">
                        <div id="category_tree"></div>
                    </div>
                    <div class="box-footer">
                        <div class="box-tools pull-right">
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
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">
                    {{ __('Scheduled and budgeted transactions for selected categories') }}
                </h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <table class="table table-bordered table-hover no-footer" id="table"></table>
            </div>
            <!-- /.box-body -->
        </div>
        <!-- /.box -->
    </div>
</div>

@include('template.components.model-delete-form')

@include('template.components.transaction-skip-form')

@stop
