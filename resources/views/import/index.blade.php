@extends('template.layouts.page')

@section('title', __('Import transactions'))

@section('content_header', __('Import transactions'))

@section('content')
    <div class="row justify-content-center mb-3">
        <div class="col-12 col-md-8 col-xl-6 col-xxl-4">
            <div id="onboarding-card">
                <onboarding-card
                    card-title="{{ __('Getting started with import') }}"
                    completed-message="{{ __('You can dismiss this widget to hide it forever.') }}"
                    topic="Import"
                ></onboarding-card>
            </div>
        </div>
    </div>

    <div id="app">
        <import-page></import-page>
        <transaction-create-standard-modal></transaction-create-standard-modal>
        <transaction-quickview-modal></transaction-quickview-modal>
    </div>

@stop
