@extends('template.layouts.auth')

@section('content')
<div class="bg-light min-vh-100 d-flex flex-row align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mb-4 mx-4">
                    <div class="card-body p-4">
                        @include('template.components.flag-bar')

                        <h2>{{ __('Reset Password') }}</h2>

                        @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                        @endif

                        <form
                                method="POST"
                                action="{{ route('password.email') }}"
                                @if(config('recaptcha.api_site_key'))
                                    id="form-with-recaptcha"
                                @endif
                        >
                            @csrf

                            @include('auth.components.email', ['autofocus' => true])

                            <button
                                    type="submit"
                                    @class([
                                        'btn',
                                        'btn-primary',
                                        'g-recaptcha' => config('recaptcha.api_site_key'),
                                    ])
                                    @if(config('recaptcha.api_site_key'))
                                        data-sitekey="{{ config('recaptcha.api_site_key') }}"
                                        data-callback="onSubmit"
                                    @endif
                            >
                                {{ __('Send Password Reset Link') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
