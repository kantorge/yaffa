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

                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf

                            <input type="hidden" name="token" value="{{ $token }}">

                            @include('auth.components.email', ['autofocus' => true])

                            @include('auth.components.password')

                            @include('auth.components.password_confirmation')

                            <button type="submit" class="btn btn-primary">
                                {{ __('Reset Password') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
