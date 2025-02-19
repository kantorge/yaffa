@extends('template.master')

{{-- Conditionally load Google Recaptcha --}}
{{-- This feature is mostly related to the sandbox mode, but it might be relevant to end-user instances, so it is not bound to the sandbox_mode setting --}}
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
