@extends('template.layouts.auth')

@section('content')
<div class="login-box">
    <div class="login-logo">
        <a href="/"><b>Y</b>affa</a>
    </div>
    <!-- /.login-logo -->
    <div class="login-box-body">
        <p class="login-box-msg text-right">
            <a href="?language=hu" class="ml-5 mr-5"><img src=" {{ asset('images/flags/hu.png') }}"></a>
            <a href="?language=en" class="ml-5 mr-5"><img src=" {{ asset('images/flags/en.png') }}"></a>
        </p>

        <p class="login-box-msg">{{ __('Sign in to start your session') }}</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group has-feedback">
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
                 @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ __($message) }}</strong>
                    </span>
                @enderror
            </div>
            <div class="form-group has-feedback">
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                @error('password')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ __($message) }}</strong>
                    </span>
                @enderror
            </div>
            <div class="row">
                <div class="col-xs-8">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                        <label class="form-check-label" for="remember">
                            {{ __('Remember Me') }}
                        </label>
                    </div>
                </div>
                <!-- /.col -->
                <div class="col-xs-4">
                    <button type="submit" class="btn btn-primary btn-block btn-flat">{{ __('Sign In') }}</button>
                </div>
                <!-- /.col -->
            </div>
        </form>
        @if (Route::has('password.request'))
            <p>
                <a href="{{ route('password.request') }}">
                    {{ __('Forgot Your Password?') }}
                </a>
            </p>
        @endif
        <p>
            {{ __('New to YAFFA?')}}
            <a href="{{ route('register') }}">
                {{ __('Register a new account') }}
            </a>
        </p>
    </div>
    <!-- /.login-box-body -->
</div>
<!-- /.login-box -->
@endsection
