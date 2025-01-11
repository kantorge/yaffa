@extends('template.master')

{{-- Add canonical link to mitigate language parameter issues via meta_tags yield --}}
@section('meta_tags')
    <link rel="canonical" href="{{ url()->current() }}">
@endsection

{{-- Conditionally load Google Recaptcha --}}
{{-- This feature is very closesly related to the sandbox mode, but it might be relevant to end-user instances, too --}}
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
