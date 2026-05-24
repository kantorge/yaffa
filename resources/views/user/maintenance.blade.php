@extends('template.layouts.page')

@section('title_postfix', __('maintenance.title'))

@section('content_container_classes', 'container-md')

@section('content_header', __('maintenance.title'))

@section('content')
    <div class="row g-3">

        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">{{ __('maintenance.currencyCache.title') }}</h5>
                        <p class="card-text text-muted mb-0">
                            {{ __('maintenance.currencyCache.description') }}
                        </p>
                    </div>
                    <div class="ms-4 flex-shrink-0">
                        <button
                            type="button"
                            class="btn btn-outline-warning maintenance-task-btn"
                            data-route="{{ route('api.v1.maintenance.clear-currency-cache') }}"
                            data-method="POST"
                        >
                            <i class="fa fa-refresh me-2"></i>
                            {{ __('maintenance.currencyCache.action') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">{{ __('maintenance.accountMonthlySummaries.title') }}</h5>
                        <p class="card-text text-muted mb-0">
                            {{ __('maintenance.accountMonthlySummaries.description') }}
                        </p>
                    </div>
                    <div class="ms-4 flex-shrink-0">
                        <button
                            type="button"
                            class="btn btn-outline-primary maintenance-task-btn"
                            data-route="{{ route('api.v1.maintenance.recalculate-account-monthly-summaries') }}"
                            data-method="POST"
                        >
                            <i class="fa fa-calculator me-2"></i>
                            {{ __('maintenance.accountMonthlySummaries.action') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">{{ __('maintenance.aiDocumentOldFiles.title') }}</h5>
                        <p class="card-text text-muted mb-0">
                            {{ __('maintenance.aiDocumentOldFiles.description', ['days' => config('ai-documents.local_storage_file_retention.retention_days')]) }}
                        </p>
                    </div>
                    <div class="ms-4 flex-shrink-0">
                        <button
                            type="button"
                            class="btn btn-outline-danger maintenance-task-btn"
                            data-route="{{ route('api.v1.maintenance.cleanup-ai-document-old-files') }}"
                            data-method="POST"
                            data-confirm-text="{{ __('maintenance.aiDocumentOldFiles.confirmText', ['days' => config('ai-documents.local_storage_file_retention.retention_days')]) }}"
                        >
                            <i class="fa fa-trash me-2"></i>
                            {{ __('maintenance.aiDocumentOldFiles.action') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
@stop
