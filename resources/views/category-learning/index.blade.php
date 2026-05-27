@extends('template.layouts.page')

@section('title_postfix',  __('Category learning'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Category learning'))

@section('content')
    <div id="categoryLearningIndex">
        <div class="row">
            <div class="col-12 col-lg-3">
                <div id="onboarding-card">
                    <onboarding-card
                        card-title="{{ __('Guided tour') }}"
                        card-body="{{ __('Use this page to review, maintain, and merge category learning entries created from your AI-assisted categorization history.') }}"
                        completed-message="{{ __('You can dismiss this widget to hide it forever.') }}"
                        topic="CategoryLearning"
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
                            {{ __('New category learning entry') }}
                            <button class="btn btn-sm btn-success" id="button-new-learning" title="{{ __('New category learning entry') }}" aria-label="{{ __('New category learning entry') }}">
                                <i class="fa fa-plus"></i>
                            </button>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ __('Merge category learning entries') }}
                            <button class="btn btn-sm btn-primary" id="button-merge-learning" title="{{ __('Merge category learning entries') }}" aria-label="{{ __('Merge category learning entries') }}">
                                <i class="fa fa-random"></i>
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
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <label class="col-4" for="table_filter_category">
                                {{ __('Category') }}
                            </label>
                            <div class="col-8">
                                <select id="table_filter_category" class="form-select form-select-sm" style="width: 100%"></select>
                            </div>
                        </li>
                        @include('template.components.tablefilter-sidebar-search')
                    </ul>
                </div>
            </div>

            <div class="col-12 col-lg-9">
                <div class="card mb-3">
                    <div class="card-body no-datatable-search">
                        <table
                            class="table table-striped table-bordered table-hover"
                            dusk="table-category-learning"
                            id="table"
                            role="grid"
                        ></table>
                    </div>
                </div>
            </div>
        </div>

        <category-learning-form
            ref="learningFormNew"
            action="new"
            id="newCategoryLearningModal"
            @learning-selected="onLearningUpserted"
        ></category-learning-form>

        <category-learning-form
            ref="learningFormEdit"
            action="edit"
            id="editCategoryLearningModal"
            @learning-selected="onLearningUpserted"
        ></category-learning-form>

        <div class="modal" tabindex="-1" id="mergeCategoryLearningModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Merge category learning entries') }}</h5>
                        <button
                            type="button"
                            class="btn-close"
                            data-coreui-dismiss="modal"
                            aria-label="Close"
                        ></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <label for="merge_source_learning" class="form-label col-sm-3">
                                {{ __('Source') }}
                            </label>
                            <div class="col-sm-9">
                                <select id="merge_source_learning" class="form-select" style="width: 100%"></select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="merge_target_learning" class="form-label col-sm-3">
                                {{ __('Target') }}
                            </label>
                            <div class="col-sm-9">
                                <select id="merge_target_learning" class="form-select" style="width: 100%"></select>
                            </div>
                        </div>
                        <div class="alert alert-info mb-0">
                            {{ __('Only category learning entries from the same category can be merged.') }}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-coreui-dismiss="modal">
                            {{ __('Close') }}
                        </button>
                        <button type="button" class="btn btn-primary" id="button-submit-merge-learning">
                            {{ __('Merge') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop