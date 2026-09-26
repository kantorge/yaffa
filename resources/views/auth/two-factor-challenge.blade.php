@extends('template.layouts.auth')

@section('content')
    <div class="auth-page-bg min-vh-100 d-flex flex-row align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card-group d-block d-md-flex row">
                        <div class="card col-md-7 p-4 mb-0">
                            <div class="card-body">
                                @include('template.components.flag-bar')

                                <h1>
                                    {{ __('Two-factor authentication') }}
                                </h1>
                                <p class="text-medium-emphasis">
                                    {{ __('Enter the 6-digit code from your authenticator app, or one of your recovery codes.') }}
                                </p>

                                <form method="POST">
                                    @csrf

                                    <div class="input-group mb-3">
                                        <span class="input-group-text">
                                            <i class="fa-solid fa-fw fa-key"></i>
                                        </span>
                                        <input
                                            @class([
                                                'form-control',
                                                'is-invalid' => $errors->has('2fa_code'),
                                            ])
                                            id="2fa_code"
                                            name="2fa_code"
                                            placeholder="{{ __('Authentication code') }}"
                                            autocomplete="one-time-code"
                                            autofocus
                                            required
                                            type="text"
                                        >
                                        @error('2fa_code')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ __($message) }}</strong>
                                            </span>
                                        @enderror
                                    </div>

                                    <button class="btn btn-primary px-4" type="submit" dusk="two-factor-challenge-button">
                                        {{ __('Verify') }}
                                    </button>
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
