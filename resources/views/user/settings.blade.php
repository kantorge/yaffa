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
                </div>
            </div>
            <div class="row mb-3">
                <label for="name" class="col-form-label col-sm-3">
                    {{ __('Locale') }}
                </label>
                <div class="col-sm-9">
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
