<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>

    {{-- Base Meta Tags --}}
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    {{-- Custom Meta Tags --}}
    @yield('meta_tags')

    {{-- Title --}}
    <title>
        @yield('title_prefix', '')
        @yield('title', 'YAFFA')
        @yield('title_postfix', '')
    </title>

    {{-- Custom stylesheets (pre AdminLTE) --}}
    @yield('adminlte_css_pre')

    <link rel="stylesheet" href="{{ mix(config('adminlte.laravel_mix_css_path', 'css/app.css')) }}">

    {{-- Custom Stylesheets (post AdminLTE) --}}
    @yield('adminlte_css')

    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}" />

</head>

<body class="hold-transition @yield('classes_body')">

    {{-- Body Content --}}
    @yield('body')

    {{-- Footer Content --}}
    @yield('footer')

@include('template.components.footer')

@routes

<!-- REQUIRED JS SCRIPTS -->
<script src="{{ mix('js/manifest.js') }}"></script>
<script src="{{ mix('js/vendor.js') }}"></script>
<script src="{{ mix('js/app.js') }}"></script>

</body>
</html>
