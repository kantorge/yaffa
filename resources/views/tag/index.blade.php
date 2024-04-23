@extends('template.layouts.page')

@section('title_postfix',  __('Tags'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Tags'))

@section('content')
    <div class="row">
        <div class="col-12 col-lg-3">
            <div class="card mb-3">
                <div class="card-header">
                    <div
                            class="card-title collapse-control"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardActions"
                    >
                        <i class="fa fa-angle-down"></i>
                        {{ __('Actions') }}
                    </div>
                </div>
                <ul
                        class="list-group list-group-flush collapse show"
                        aria-expanded="true"
                        id="cardActions"
                >
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('New tag') }}
                        <a class="btn btn-success btn-sm"
                           dusk="button-new-tag"
                           href="{{ route('tag.create') }}" title="{{ __('New tag') }}"
                        >
                            <i class="fa fa-fw fa-plus" title="{{ __('New tag') }}"></i>
                        </a>
                    </li>
                </ul>
            </div>

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
                    />
                    @include('template.components.tablefilter-sidebar-search')
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-9">
            <div class="card mb-3">
                <div class="card-body no-datatable-search">
                    <table
                            class="table table-striped table-bordered table-hover"
                            dusk="table-tags"
                            id="table"
                            role="grid"
                    ></table>
                </div>
            </div>
        </div>
    </div>

    @include('template.components.model-delete-form')
@stop
