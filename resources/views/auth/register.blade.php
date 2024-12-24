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
                        <form
                                method="POST"
                                action="{{ route('register') }}"
                                autocomplete="off"
                                @if(config('recaptcha.api_site_key'))
                                    id="form-with-recaptcha"
                                @endif
                        >
                            @csrf

                            @include('auth.components.email', ['autofocus' => false])

                            <div class="input-group mb-3">
                                <span class="input-group-text">
                                    <i class="fa-regular fa-fw fa-user"></i>
                                </span>
                                <input
                                    @class([
                                        'form-control',
                                        'is-invalid' => $errors->has('name'),
                                    ])
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

                            <div class="form-check mb-3">
                                <input
                                        @class([
                                            "form-check-input",
                                        ])
                                        name="tos"
                                        required
                                        type="checkbox"
                                        value="yes"
                                        id="tos"
                                >
                                <label
                                        @class([
                                            "form-check-label",
                                            "is-invalid" => $errors->has('tos'),
                                        ])
                                        for="tos"
                                >
                                    {!! __('I accept the <a href=":toslink" target="_blank">terms of service</a>',
                                            ['toslink' => route('terms')]
                                            ) !!}
                                </label>
                                @error('tos')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <h4
                                class="collapse-control collapsed mt-3"
                                data-coreui-toggle="collapse"
                                data-coreui-target="#customizeOnboarding"
                            >
                                <i class="fa fa-angle-down"></i>
                                {{ __('Customize YAFFA') }}
                            </h4>
                            <div class="collapse" aria-expanded="false" id="customizeOnboarding">
                                <div class="input-group mb-3">
                                    <span class="input-group-text">
                                        <i class="fa-solid fa-fw fa-language"></i>
                                        <span class="ms-2 d-none d-md-block">{{ __('Language') }}</span>
                                    </span>
                                    <select
                                        class="form-select @error('language') is-invalid @enderror"
                                        id="language"
                                        name="language"
                                    >
                                        @foreach ($languages as $code => $language)
                                            <option
                                                value="{{ $code }}"
                                                @if ((old() && old('language') === $code) || Config::get('app.locale') === $code)
                                                    selected="selected"
                                                @endif
                                            >
                                                {{ __($language) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span
                                        class="input-group-text btn btn-info"
                                        data-coreui-toggle="tooltip"
                                        data-coreui-placement="right"
                                        title="{{ __('Controls the language used in YAFFA.') }}"
                                    >
                                        <i class="fa fa-info-circle"></i>
                                    </span>

                                    @error('language')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ __($message) }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="input-group mb-3">
                                    <span class="input-group-text">
                                        <i class="fa-solid fa-fw fa-globe"></i>
                                        <span class="ms-2 d-none d-md-block">{{ __('Locale') }}</span>
                                    </span>
                                    <select
                                        class="form-select @error('locale') is-invalid @enderror"
                                        id="locale"
                                        name="locale"
                                    >
                                        @foreach ($locales as $code => $locale)
                                            <option value="{{ $code }}"
                                                @if ((old() && old('locale') === $code) || Config::get('app.locale') === $code)
                                                    selected="selected"
                                                @endif
                                            >
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
                                        <i class="fa fa-info-circle"></i>
                                    </span>

                                    @error('locale')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ __($message) }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="input-group mb-3">
                                    <span class="input-group-text">
                                        <i class="fa-solid fa-fw fa-database"></i>
                                        <span class="ms-2 d-none d-md-block">{{ __('Starting assets') }}</span>
                                    </span>
                                    <select
                                            class="form-select @error('default_data') is-invalid @enderror"
                                            id="default_data"
                                            name="default_data"
                                    >
                                        @foreach ($defaultAssetOptions as $key => $value)
                                            <option value="{{ $key }}"
                                                @if (old() && old('default_data') == $key) selected="selected"@endif>
                                                {{ __($value) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span
                                            class="input-group-text btn btn-info"
                                            data-coreui-toggle="tooltip"
                                            data-coreui-placement="right"
                                            title="{{ __('YAFFA can create some accounts, currencies and categories for a convenient start. You can customize these anytime.') }}"
                                    >
                                        <i class="fa fa-info-circle"></i>
                                    </span>

                                    @error('default_data')
                                    <span class="invalid-feedback" role="alert">
                                            <strong>{{ __($message) }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="input-group mb-3">
                                    <span class="input-group-text">
                                        <i class="fa-solid fa-fw fa-money-bill"></i>
                                        <span class="ms-2 d-none d-md-block">{{ __('Base currency') }}</span>
                                    </span>
                                    <select
                                            class="form-select @error('base_currency') is-invalid @enderror"
                                            id="base_currency"
                                            name="base_currency"
                                    >
                                        @foreach ($availableCurrencies as $key => $value)
                                            <option
                                                    value="{{ $key }}"
                                                    @if (old() && old('base_currency') === $key)
                                                        selected="selected"
                                                    @endif
                                            >
                                                {{ __($value) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span
                                            class="input-group-text btn btn-info"
                                            data-coreui-toggle="tooltip"
                                            data-coreui-placement="right"
                                            title="{{ __('The currency that is generally used in reports, charts and summaries. You can add several other currencies to fit your needs, and change this setting later.') }}"
                                    >
                                        <i class="fa fa-info-circle"></i>
                                    </span>

                                    @error('base_currency')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ __($message) }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <button
                                    @class([
                                        'btn',
                                        'btn-block',
                                        'btn-success',
                                        'mt-3',
                                        'g-recaptcha' => config('recaptcha.api_site_key'),
                                    ])
                                    type="submit"
                                    @if(config('recaptcha.api_site_key'))
                                        data-sitekey="{{ config('recaptcha.api_site_key') }}"
                                        data-callback="onSubmit"
                                    @endif
                            >
                                {{ __('Register') }}
                            </button>
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
