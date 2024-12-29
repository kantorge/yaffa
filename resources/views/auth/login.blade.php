@extends('template.layouts.auth')

@section('content')
<div class="bg-light min-vh-100 d-flex flex-row align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card-group d-block d-md-flex row">
                    <div class="card col-md-7 p-4 mb-0">
                        <div class="card-body">
                            @include('template.components.flag-bar')

                            <h1>
                                {{ __('Login') }}
                            </h1>
                            @if (config('yaffa.sandbox_mode'))
                                <p class="text-medium-emphasis">
                                    {{ __('sandbox.login.info') }}
                                </p>
                                <p class="text-medium-emphasis">
                                    {!! __('sandbox.login.credentials') !!}
                                    <button
                                            class="btn btn-sm"
                                            type="button"
                                            title="{{ __('sandbox.login.fill') }}"
                                            data-coreui-toggle="tooltip"
                                            id="loginWithDemoCredentials"
                                    >
                                        <i class="fa-solid fa-right-to-bracket"></i>
                                    </button>
                                </p>
                            @else
                                <p class="text-medium-emphasis">
                                    {{ __('Sign in to start your session') }}
                                </p>
                            @endif
                            <form
                                    method="POST"
                                    action="{{ route('login') }}"
                                    @if(config('recaptcha.api_site_key'))
                                        id="form-with-recaptcha"
                                    @endif
                            >
                                @csrf

                                @include('auth.components.email', ['autofocus' => true])

                                @include('auth.components.password')

                                <div class="row">
                                    <div class="col-5">
                                        <button
                                                @class([
                                                    'btn',
                                                    'btn-primary',
                                                    'px-4',
                                                    'g-recaptcha' => config('recaptcha.api_site_key'),
                                                ])
                                                type="submit"
                                                dusk="login-button"
                                                id="login"
                                                @if(config('recaptcha.api_site_key'))
                                                    data-sitekey="{{ config('recaptcha.api_site_key') }}"
                                                    data-callback="onSubmit"
                                                @endif
                                        >
                                            {{ __('Login') }}
                                        </button>
                                    </div>
                                    <div class="col-7 text-end">
                                        <a href="{{ route('password.request') }}" class="btn" role="button">
                                            {{ __('Forgot Your Password?') }}
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card col-md-5 text-white bg-primary py-5">
                        <div class="card-body text-center">
                            <div>
                                <h2>
                                    <img src="{{ asset('images/logo-small.png')}}" alt="YAFFA Logo">
                                    YAFFA
                                </h2>
                                <p>
                                    {{ __('YAFFA is an easy to use personal finance tracker.') }}
                                </p>
                                <a href="{{ route('register') }}" class="btn btn-lg btn-light mt-3">
                                    {{ __('Register a new account') }}
                                </a>
                                <a
                                        href="https://www.yaffa.cc/"
                                        target="_blank"
                                        class="btn btn-lg btn-link text-light mt-3"
                                >
                                    {{ __('Learn more') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
