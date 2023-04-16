@extends('template.layouts.page')

@section('title_postfix', __('Investment summary'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Investment summary'))

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            @include('template.components.tablefilter-active')
            <div>
                <a
                        class="btn btn-success"
                        href="{{ route('investment.create') }}"
                        title="{{ __('New investment') }}"
                >
                    <i class="fa fa-plus"></i>
                </a>
            </div>
        </div>
        <div class="card-body">
            <table
                    class="table table-striped table-bordered table-hover"
                    id="investmentSummary"
                    role="grid"
                    aria-label="{{ __('List of investments') }}"
            ></table>
        </div>
    </div>
@stop
