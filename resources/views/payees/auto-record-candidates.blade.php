@extends('template.layouts.page')

@section('title_postfix', __('Auto-record candidates'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Auto-record candidates'))

@section('content')
    <div class="row">
        <div class="col-12 col-lg-3">
            <div class="card mb-3">
                <div class="card-header">
                    <div
                            class="card-title collapse-control"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardFilters"
                    >
                        <i class="fa fa-angle-down"></i>
                        {{ __('Filters') }}
                    </div>
                </div>
                <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardFilters">
                    <x-tablefilter-sidebar-switch
                            label=" {{ __('Active') }}"
                            property="active"
                            default="yes"
                    />
                    <x-tablefilter-sidebar-switch
                            label=" {{ __('Has active schedule') }}"
                            property="has_active_schedule"
                            default="no"
                    />
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-9">
            <div id="autoRecordCandidates"></div>
        </div>
    </div>
@stop
