@extends('template.layouts.page')

@section('title_postfix',  __('Categories'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Categories'))

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
                        {{ __('New category') }}
                        <a class="btn btn-sm btn-success"
                           dusk="button-new-category"
                           href="{{ route('categories.create') }}"
                           title="{{ __('New category') }}"
                        >
                            <i class="fa fa-plus"></i>
                        </a>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Merge categories') }}
                        <a class="btn btn-sm btn-primary"
                           dusk="button-merge-categories"
                           href="{{ route('categories.merge.form') }}"
                           title="{{ __('Merge categories') }}"
                        >
                            <i class="fa fa-random"></i>
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
                    @include('template.components.tablefilter-sidebar-category-parent')
                    @include('template.components.tablefilter-sidebar-search')
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-9">
            <div class="card mb-3">
                <div class="card-body no-datatable-search">
                    <table
                            class="table table-striped table-bordered table-hover"
                            dusk="table-categories"
                            id="table"
                            role="grid"
                    ></table>
                </div>
            </div>
        </div>
    </div>
@stop
