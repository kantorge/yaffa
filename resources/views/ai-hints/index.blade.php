@extends('template.layouts.page')

@section('title_postfix',  __('AI hints'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('AI hints'))

@section('content')
    <div id="aiHintsIndex">
        <div class="row">
            <div class="col-12 col-lg-3">
                <div id="onboarding-card">
                    <onboarding-card
                        card-title="{{ __('Guided tour') }}"
                        card-body="{{ __('AI hints are guidelines sent to the AI when extracting a document. A hint is only used if its trigger text appears in the document, or always if the trigger text is empty.') }}"
                        completed-message="{{ __('You can dismiss this widget to hide it forever.') }}"
                        topic="AiHints"
                    ></onboarding-card>
                </div>

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
                            {{ __('New AI hint') }}
                            <button class="btn btn-sm btn-success" id="button-new-hint" title="{{ __('New AI hint') }}" aria-label="{{ __('New AI hint') }}">
                                <i class="fa fa-plus"></i>
                            </button>
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
                            id="table"
                            role="grid"
                        ></table>
                    </div>
                </div>
            </div>
        </div>

        <ai-hint-form
            ref="hintFormNew"
            action="new"
            id="newAiHintModal"
            @hint-saved="onHintSaved"
        ></ai-hint-form>

        <ai-hint-form
            ref="hintFormEdit"
            action="edit"
            id="editAiHintModal"
            @hint-saved="onHintSaved"
        ></ai-hint-form>
    </div>
@stop
