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

        <p class="register-box-msg">{{ __('Verify Your Email Address') }}</p>

        <div class="card-body">
            @if (session('message'))
                <div class="alert alert-success" role="alert">
                    {{ __('A fresh verification link has been sent to your email address.') }}
                </div>
            @endif

            {{ __('Before proceeding, please check your email for a verification link.') }}
            {{ __('If you did not receive the email') }},
            <form class="d-inline" method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-link p-0 m-0 align-baseline">{{ __('click here to request another') }}</button>.
            </form>
        </div>

    </div>
</div>
@endsection
