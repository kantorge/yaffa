@extends('template.layouts.page')

@section('title', __('Merge categories'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Merge categories'))

@section('content')

    <form
        accept-charset="UTF-8"
        action="{{ route('categories.merge.submit') }}"
        autocomplete="off"
        id="merge-categories-form"
        method="POST"
    >
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    {{ __('Select categories to merge') }}
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="category_source" class="form-label">
                                {{ __('Category to be merged') }}
                            </label>
                            <select
                                class="form-select"
                                id="category_source"
                                name="category_source"
                            ></select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="category_target" class="form-label">
                                {{ __('Where to merge category') }}
                            </label>
                            <select
                                class="form-select"
                                id="category_target"
                                name="category_target"
                            ></select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="row mb-3">
                            <label class="form-label">
                                {{ __('After merging') }}
                            </label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="action" value="delete" checked="checked">
                                    <label class="form-check-label">
                                        {{ __('Delete merged category') }}
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="action" value="close" checked="">
                                    <label class="form-check-label">
                                        {{ __('Set merged category to inactive') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <span class="form-label d-block">{{ __('This action cannot be undone. Proceed with caution.') }}</span>
                        @csrf
                        <button
                            class="btn btn-sm btn-outline-dark"
                            type="button"
                            id="cancel"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <input class="btn btn-primary" type="submit" value="{{ __('Merge categories') }}">
                    </div>
                </div>
            </div>
        </div>
    </form>

@stop
