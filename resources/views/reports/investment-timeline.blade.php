@extends('template.layouts.page')

@section('title_postfix', __('Investment timeline'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Investment timeline'))

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
                <ul class="list-group list-group-flush collapse show" id="cardFilters">
                    <x-tablefilter-sidebar-switch
                            label=" {{ __('Active') }}"
                            property="active"
                    />
                    <x-tablefilter-sidebar-switch
                            label=" {{ __('Open') }}"
                            property="open"
                    />
                    @include('template.components.tablefilter-sidebar-search')
                    <li class="list-group-item">
                        <div id="investment-group-tree-container"></div>
                    </li>
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-9">
            <div class="card mb-3">
                <div class="card-body">
                    <div id="chart-placeholder" style="width: 100%; height: 700px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-spinner fa-spin" style="font-size: 24px; margin-right: 10px;"></i>
                        <span>{{ __('Loading chart...') }}</span>
                    </div>
                    <div id="chart" style="width: 100%; height: 700px; display: none;"></div>
                </div>
            </div>
        </div>
    </div>
@stop
