@extends('template.master')

@section('head_scripts')
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script>
        function onSubmit(token) {
            document.getElementById("form-with-recaptcha").submit();
        }
    </script>
@endsection

@section('body')

    @include('template.components.notifications')

    @yield('content')

@endsection
