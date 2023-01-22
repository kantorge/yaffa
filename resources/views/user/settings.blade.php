@extends('template.layouts.page')

@section('title_postfix',  __('User settings'))

@section('content_container_classes', 'container-md')

@section('content_header', __('User settings'))

@section('content')
<form
    accept-charset="UTF-8"
    action="{{ route('user.update') }}"
    autocomplete="off"
    method="POST"
>
<input name="_method" type="hidden" value="PATCH">

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                {{ __('Update user settings') }}
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <label for="name" class="col-form-label col-sm-3">
                    {{ __('Language') }}
                </label>
                <div class="col-sm-9">
                    <div class="input-group">
                        <select
                            class="form-select"
                            id="language"
                            name="language"
                        >
                            @foreach($languages as $code => $language)
                                <option
                                    value="{{ $code }}"
                                    @if (old() && old('language') == $code)
                                        selected="selected"
                                    @elseif(Auth::user()->language === $code)
                                        selected="selected"
                                    @endif
                                >
                                    {{ $language }}
                                </option>
                            @endforeach
                        </select>
                        <span
                            class="input-group-text btn btn-info"
                            data-coreui-toggle="tooltip"
                            data-coreui-placement="top"
                            title="{{ __('Controls the language used in YAFFA.') }}"
                        >
                            <i
                                class="fa fa-info-circle"
                            ></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <label for="name" class="col-form-label col-sm-3">
                    {{ __('Locale') }}
                </label>
                <div class="col-sm-9">
                    <div class="input-group">
                        <select
                            class="form-select"
                            id="locale"
                            name="locale"
                        >
                            @foreach($locales as $code => $language)
                                <option
                                    value="{{ $code }}"
                                    @if (old() && old('locale') == $code)
                                        selected="selected"
                                    @elseif(Auth::user()->locale === $code)
                                        selected="selected"
                                    @endif
                                >
                                    {{ $language }}
                                </option>
                        @endforeach
                        </select>
                        <span
                            class="input-group-text btn btn-info"
                            data-coreui-toggle="tooltip"
                            data-coreui-placement="top"
                            title="{{ __('Controls how numbers, dates, currencies are formatted.') }}"
                        >
                            <i
                                class="fa fa-info-circle"
                            ></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <label for="name" class="col-form-label col-sm-3">
                    {{ __('Start date for YAFFA') }}
                </label>
                <div class="col-sm-9">
                    <div class="input-group">
                        <input
                            autocomplete="off"
                            class="form-control"
                            id="start_date"
                            name="start_date"
                            placeholder="{{ __('Select date') }}"
                            type="text"
                            value="{{old('start_date', Auth::user()->start_date )}}"
                        >
                        <span
                            class="input-group-text btn btn-info"
                            data-coreui-toggle="tooltip"
                            data-coreui-placement="top"
                            title="{{ __('The earliest date YAFFA uses to retrieve currency exchange rates and investment prices. You can record transactions to earlier dates, if needed.') }}"
                        >
                            <i
                                class="fa fa-info-circle"
                            ></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <label for="name" class="col-form-label col-sm-3">
                    {{ __('End date for YAFFA') }}
                </label>
                <div class="col-sm-9">
                    <div class="input-group">
                        <input
                            autocomplete="off"
                            class="form-control"
                            id="end_date"
                            name="end_date"
                            placeholder="{{ __('Select date') }}"
                            type="text"
                            value="{{old('end_date', Auth::user()->end_date )}}"
                        >
                        <span
                            class="input-group-text btn btn-info"
                            data-coreui-toggle="tooltip"
                            data-coreui-placement="top"
                            title="{{ __('How long would you like YAFFA to calculate forecasts.') }}"
                        >
                            <i
                                class="fa fa-info-circle"
                            ></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            @csrf

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
        </div>
    </div>
</form>
@stop
