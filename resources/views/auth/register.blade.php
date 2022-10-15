@extends('template.layouts.auth')

@section('content')
<div class="register-box">
    <div class="register-logo">
        <a href="/"><b>Y</b>affa</a>
    </div>
    <!-- /.login-logo -->

    <div class="register-box-body">
        <p class="register-box-msg text-right">
            <a href="?language=hu" class="ml-5 mr-5"><img src=" {{ asset('images/flags/hu.png') }}"></a>
            <a href="?language=en" class="ml-5 mr-5"><img src=" {{ asset('images/flags/en.png') }}"></a>
        </p>

        <p class="register-box-msg">{{ __('Register') }}</p>
            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="form-group has-feedback row">
                    <label for="name" class="col-md-5 col-form-label text-md-right">{{ __('Name') }}</label>

                    <div class="col-md-7">
                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                        @error('name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="form-group has-feedback row">
                    <label for="email" class="col-md-5 col-form-label text-md-right">{{ __('E-Mail Address') }}</label>

                    <div class="col-md-7">
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

                        @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="form-group has-feedback row">
                    <label for="password" class="col-md-5 col-form-label text-md-right">{{ __('Password') }}</label>

                    <div class="col-md-7">
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">

                        @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>

                <div class="form-group has-feedback row">
                    <label for="password-confirm" class="col-md-5 col-form-label text-md-right">{{ __('Confirm Password') }}</label>

                    <div class="col-md-7">
                        <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <div class="form-group has-feedback row">
                    <label for="language" class="col-md-5 col-form-label text-md-right">{{ __('Language') }}</label>

                    <div class="col-md-7">
                        <select
                        class="form-control"
                        id="language"
                        name="language"
                    >
                        @foreach($languages as $code => $language)
                            <option
                                value="{{ $code }}"
                                @if (old() && old('language') == $code)
                                    selected="selected"
                                @elseif(Config::get('app.locale') === $code)
                                    selected="selected"
                                @endif
                            >
                                {{ $language }}
                            </option>
                        @endforeach
                    </select>
                    </div>
                </div>

                <div class="form-group has-feedback row">
                    <label for="locale" class="col-md-5 col-form-label text-md-right">{{ __('Locale') }}</label>

                    <div class="col-md-7">
                        <select
                        class="form-control"
                        id="locale"
                        name="locale"
                    >
                        @foreach($locales as $code => $locale)
                            <option
                                value="{{ $code }}"
                                @if (old() && old('locale') == $code)
                                    selected="selected"
                                @elseif(Config::get('app.locale') === $code)
                                    selected="selected"
                                @endif
                            >
                                {{ $locale }}
                            </option>
                        @endforeach
                    </select>
                    </div>
                </div>

                <div class="form-group row mb-0">
                    <div class="col-md-6 offset-md-4">
                        <button type="submit" class="btn btn-primary">
                            {{ __('Register') }}
                        </button>
                    </div>
                </div>

                <div class="form-group row">
                    <div class="col-md-12">
                        {{ __('Do you have an account?') }}
                        <a href="{{ route('login')}}">{{ __('Click here to sign in') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
