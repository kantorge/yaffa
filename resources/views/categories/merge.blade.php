@extends('template.layouts.page')

@section('title', __('Merge categories'))

@section('content_header', __('Merge categories'))

@section('content')

    <form
        accept-charset="UTF-8"
        action="{{ route('categories.merge.submit') }}"
        autocomplete="off"
        id="merge-categories-form"
        method="POST"
    >
        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title">
                    {{ __('Select categories to merge') }}
                </h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="category_source">
                                {{ __('Category to be merged') }}
                            </label>
                            <select
                                class="form-control"
                                id="category_source"
                                name="category_source"
                            ></select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="category_target">
                                {{ __('Where to merge category') }}
                            </label>
                            <select
                                class="form-control"
                                id="category_target"
                                name="category_target"
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
                                    {{ __('Delete merged category') }}
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="action" value="close" checked="">
                                    {{ __('Set merged category to inactive') }}
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
                            <input class="btn btn-primary" type="submit" value="{{ __('Merge categories') }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

@stop
