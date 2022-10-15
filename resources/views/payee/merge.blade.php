@extends('template.layouts.page')

@section('title', __('Merge payees'))

@section('content_header', __('Merge payees'))

@section('content')

    <form
        accept-charset="UTF-8"
        action="{{ route('payees.merge.submit') }}"
        autocomplete="off"
        id="merge-payees-form"
        method="POST"
    >
        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title">
                    {{ __('Select payees to merge') }}
                </h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="payee_source">
                                {{ __('Payee to be merged') }}
                            </label>
                            <select
                                class="form-control"
                                id="payee_source"
                                name="payee_source"
                            ></select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="payee_target">
                                {{ __('Where to merge payee') }}
                            </label>
                            <select
                                class="form-control"
                                id="payee_target"
                                name="payee_target"
                            ></select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group form-horizontal">
                            <label>
                                {{ __('After merging') }}
                            </label>
                            <div class="radio">
                                <label class="radio-inline">
                                    <input type="radio" name="action" value="delete" checked="checked">
                                    {{ __('Delete merged payee') }}
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="action" value="close" checked="">
                                    {{ __('Set merged payee to inactive') }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group has-error">
                            <span class="help-block">{{ __('This action cannot be undone. Proceed with caution.') }}</span>
                            @csrf
                            <button
                                class="btn btn-sm btn-default"
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
        </div>
    </form>

@stop
