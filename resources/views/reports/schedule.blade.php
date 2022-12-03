@extends('template.layouts.page')

@section('title_postfix', __('Scheduled transactions'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Scheduled and budgeted transactions'))

@section('content')
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-12">
                <div class="form-group d-inline-block">
                    <label class="control-label">
                        {{ __('Schedule') }}
                    </label>
                    <div>
                        <div class="btn-group" data-toggle="buttons">
                            <label class="btn btn-primary" title="{{ __('Yes') }}">
                                <input type="radio" name="schedule" value="{{ __('Yes') }}" class="btn-check">
                                <span class="fa fa-fw fa-check"></span>
                            </label>
                            <label class="btn btn-primary active" title="{{ __('Any') }}">
                                <input type="radio" name="schedule" value="" class="btn-check" checked="checked">
                                <span class="fa fa-fw fa-circle"></span>
                            </label>
                            <label class="btn btn-primary" title="{{ __('No') }}">
                                <input type="radio" name="schedule" value="{{ __('No') }}" class="btn-check">
                                <span class="fa fa-fw fa-close"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-group d-inline-block">
                    <label class="control-label">
                        {{ __('Budget') }}
                    </label>
                    <div>
                        <div class="btn-group" data-toggle="buttons">
                            <label class="btn btn-primary" title="{{ __('Yes') }}">
                                <input type="radio" name="budget" value="{{ __('Yes') }}" class="btn-check">
                                <span class="fa fa-fw fa-check"></span>
                            </label>
                            <label class="btn btn-primary active" title="{{ __('Any') }}">
                                <input type="radio" name="budget" value="" class="btn-check" checked="checked">
                                <span class="fa fa-fw fa-circle"></span>
                            </label>
                            <label class="btn btn-primary" title="{{ __('No') }}">
                                <input type="radio" name="budget" value="{{ __('No') }}" class="btn-check">
                                <span class="fa fa-fw fa-close"></span>
                            </label>
                        </div>
                    </div>
                </div>
                @include('template.components.tablefilter-active')
            </div>
        </div>

        <table class="table table-bordered table-hover no-footer" id="table"></table>
    </div>
</div>

@include('template.components.model-delete-form')
@include('template.components.transaction-skip-form')

@stop
