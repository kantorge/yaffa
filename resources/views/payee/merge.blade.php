@extends('template.layouts.page')

@section('title', __('Merge payees'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Merge payees'))

@section('content')
    <form
        accept-charset="UTF-8"
        action="{{ route('payees.merge.submit') }}"
        autocomplete="off"
        id="merge-payees-form"
        method="POST"
    >
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    {{ __('Select payees to merge') }}
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="row mb-3">
                            <label class="form-label" for="payee_source">
                                {{ __('Payee to be merged') }}
                            </label>
                            <select
                                class="form-select"
                                id="payee_source"
                                name="payee_source"
                            ></select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row mb-3">
                            <label class="form-label" for="payee_target">
                                {{ __('Where to merge payee') }}
                            </label>
                            <select
                                class="form-select"
                                id="payee_target"
                                name="payee_target"
                            ></select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="row mb-3">
                            <label>
                                {{ __('After merging') }}
                            </label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="action" value="delete" checked="checked">
                                    <label class="form-check-label">
                                        {{ __('Delete merged payee') }}
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="action" value="close" checked="">
                                    <label class="form-check-label">
                                        {{ __('Set merged payee to inactive') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <span class="help-block">{{ __('This action cannot be undone. Proceed with caution.') }}</span>
                        @csrf
                        <button
                            class="btn btn-sm btn-outline-dark ms-2 me-2"
                            type="button"
                            id="cancel"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <input class="btn btn-primary" type="submit" value="{{ __('Merge payees') }}">
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop
