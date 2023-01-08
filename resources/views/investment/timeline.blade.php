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
                        <div class="btn-group" role="group" aria-label="Toggle button group for open">
                            <input type="radio" class="btn-check" name="open" id="open_yes" value="{{ __('Yes') }}">
                            <label class="btn btn-outline-primary" for="open_yes" title="{{ __('Yes') }}">
                                <span class="fa fa-fw fa-check"></span>
                            </label>

                            <input type="radio" class="btn-check" name="open" id="open_any" value="" checked>
                            <label class="btn btn-outline-primary" for="open_any" title="{{ __('Any') }}">
                                <span class="fa fa-fw fa-circle"></span>
                            </label>

                            <input type="radio" class="btn-check" name="open" id="open_no" value="{{ __('No') }}">
                            <label class="btn btn-outline-primary" for="open_no" title="{{ __('No') }}">
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
