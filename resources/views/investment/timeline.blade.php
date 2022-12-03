@extends('template.layouts.page')

@section('title_postfix', __('Investment timeline'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Investment timeline'))

@section('content')
<div class="card">
    <div class="card-body">
        <div class="row" id="filters">
            <div class="col-lg-12">
                @include('template.components.tablefilter-active')
                <div class="d-inline-block">
                    <label>
                        {{ __('Open') }}
                    </label>
                    <div>
                        <div class="btn-group" role="group" data-toggle="buttons">
                            <label class="btn btn-primary" title="{{ __('Yes') }}">
                                <input type="radio" class="btn-check" name="open" value="{{ __('Yes') }}">
                                <span class="fa fa-fw fa-check"></span>
                            </label>
                            <label class="btn btn-primary active" title="{{ __('Any') }}">
                                <input type="radio" name="open" value="" class="btn-check" checked="checked">
                                <span class="fa fa-fw fa-circle"></span>
                            </label>
                            <label class="btn btn-primary" title="{{ __('No') }}">
                                <input type="radio" class="btn-check" name="open" value="{{ __('No') }}">
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
