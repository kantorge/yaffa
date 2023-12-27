@extends('template.master')

{{-- Conditionally load Google Recaptcha --}}
@if(config('recaptcha.api_site_key'))
@section('head_scripts')
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script>
        function onSubmit() {
            document.getElementById("form-with-recaptcha").submit();
        }
    </script>
@endsection
@endif

@section('body')

    @include('template.components.notifications')

    @yield('content')

@endsection
