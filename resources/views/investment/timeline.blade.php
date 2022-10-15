@extends('template.layouts.page')

@section('title', __('Investment timeline'))

@section('content_header', __('Investment timeline'))

@section('content')

<div class="box">
    <div class="box-body">
        <div class="row" id="filters">
            <div class="col-lg-12">
                @include('template.components.tablefilter-active')
                <div class="form-group d-inline-block">
                    <label class="control-label">
                        {{ __('Open') }}
                    </label>
                    <div>
                        <div class="btn-group" data-toggle="buttons">
                            <label class="btn btn-primary" title="{{ __('Yes') }}">
                                <input type="radio" name="open" value="Yes" class="radio-inline">
                                <span class="fa fa-fw fa-check"></span>
                            </label>
                            <label class="btn btn-primary active" title="{{ __('Any') }}">
                                <input type="radio" name="open" value="All" class="radio-inline" checked="checked">
                                <span class="fa fa-fw fa-circle-o"></span>
                            </label>
                            <label class="btn btn-primary" title="{{ __('No') }}">
                                <input type="radio" name="open" value="No" class="radio-inline">
                                <span class="fa fa-fw fa-close"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="chart" style="width: 100%; height: 700px"></div>
    </div>
</div>

@stop
