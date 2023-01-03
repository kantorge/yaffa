@extends('template.layouts.auth')

@section('content')
<div class="bg-light min-vh-100 d-flex flex-row align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mb-4 mx-4">
                    <div class="card-body p-4">
                        @include('template.components.flag-bar')
                        <h1>{{ __('Register') }}</h1>
                        <p class="text-medium-emphasis">{{ __('Create an account to start using YAFFA') }}</p>
                        <form method="POST" action="{{ route('register') }}" autocomplete="off">
                            @csrf

                            @include('auth.components.email', ['autofocus' => false])

                            <div class="input-group mb-3">
                                <span class="input-group-text">
                                    <i class="fa-regular fa-user"></i>
                                </span>
                                <input
                                    class="form-control @error('name') is-invalid @enderror"
                                    id="name"
                                    name="name"
                                    placeholder="{{ __('Name') }}"
                                    required
                                    type="text"
                                    value="{{ old('name') }}"
                                >
                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ __($message) }}</strong>
                                    </span>
                                @enderror
                            </div>

                            @include('auth.components.password')

                            @include('auth.components.password_confirmation')

                            <div class="input-group mb-3">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-language me-2"></i>
                                    {{ __('Language') }}
                                </span>
                                <select
                                    class="form-select  @error('language') is-invalid @enderror"
                                    id="language"
                                    name="language"
                                >
                                    @foreach ($languages as $code => $language)
                                        <option value="{{ $code }}"
                                            @if (old() && old('language') == $code) selected="selected"
                                        @elseif(Config::get('app.locale') === $code)
                                            selected="selected" @endif>
                                            {{ $language }}
                                        </option>
                                    @endforeach
                                </select>
                                <span
                                    class="input-group-text btn btn-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="right"
                                    title="{{ __('Controls the language used in YAFFA.') }}"
                                >
                                    <i
                                        class="fa fa-info-circle"
                                    ></i>
                                </span>

                                @error('language')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ __($message) }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="input-group mb-3">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-globe me-2"></i>
                                    {{ __('Locale') }}
                                </span>
                                <select
                                    class="form-select @error('locale') is-invalid @enderror"
                                    id="locale"
                                    name="locale"
                                >
                                    @foreach ($locales as $code => $locale)
                                        <option value="{{ $code }}"
                                            @if (old() && old('locale') == $code) selected="selected"
                                        @elseif(Config::get('app.locale') === $code)
                                            selected="selected" @endif>
                                            {{ $locale }}
                                        </option>
                                    @endforeach
                                </select>
                                <span
                                    class="input-group-text btn btn-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="right"
                                    title="{{ __('Controls how numbers, dates, currencies are formatted.') }}"
                                >
                                    <i
                                        class="fa fa-info-circle"
                                    ></i>
                                </span>

                                @error('locale')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ __($message) }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="form-check mb-3">
                                <input
                                    class="form-check-input"
                                    name="tos"
                                    required
                                    type="checkbox"
                                    value="yes"
                                     id="tos"
                                >
                                <label class="form-check-label" for="tos">
                                    {!! __('I accept the <a href=":toslink" target="_blank">terms of service</a>', ['toslink' => route('terms')]) !!}
                                </label>
                                @error('tos')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <button class="btn btn-block btn-success" type="submit">{{ __('Register') }}</button>
                        </form>
                        <p class="text-medium-emphasis mt-3">
                            {{ __('Do you have an account?') }}
                            <a href="{{ route('login') }}">{{ __('Click here to sign in') }}</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
