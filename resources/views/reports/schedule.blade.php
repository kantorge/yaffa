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
                        <div class="btn-group" role="group" aria-label="Toggle button group for schedules">
                            <input type="radio" class="btn-check" name="schedule" id="schedule_yes" value="{{ __('Yes') }}">
                            <label class="btn btn-outline-primary" for="schedule_yes" title="{{ __('Yes') }}">
                                <span class="fa fa-fw fa-check"></span>
                            </label>

                            <input type="radio" class="btn-check" name="schedule" id="schedule_any" value="" checked>
                            <label class="btn btn-outline-primary" for="schedule_any" title="{{ __('Any') }}">
                                <span class="fa fa-fw fa-circle"></span>
                            </label>

                            <input type="radio" class="btn-check" name="schedule" id="schedule_no" value="{{ __('No') }}">
                            <label class="btn btn-outline-primary" for="schedule_no" title="{{ __('No') }}">
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
                        <div class="btn-group" role="group" aria-label="Toggle button group for budgets">
                            <input type="radio" class="btn-check" name="budget" id="budget_yes" value="{{ __('Yes') }}">
                            <label class="btn btn-outline-primary" for="budget_yes" title="{{ __('Yes') }}">
                                <span class="fa fa-fw fa-check"></span>
                            </label>

                            <input type="radio" class="btn-check" name="budget" id="budget_any" value="" checked>
                            <label class="btn btn-outline-primary" for="budget_any" title="{{ __('Any') }}">
                                <span class="fa fa-fw fa-circle"></span>
                            </label>

                            <input type="radio" class="btn-check" name="budget" id="budget_no" value="{{ __('No') }}">
                            <label class="btn btn-outline-primary" for="budget_no" title="{{ __('No') }}">
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
