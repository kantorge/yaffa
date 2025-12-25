<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @includeWhen(config('yaffa.sandbox_mode'), 'template.sandbox-components.head')

    {{-- Optional header scripts --}}
    @yield('head_scripts')

    {{-- Base Meta Tags --}}
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Custom Meta Tags --}}
    @yield('meta_tags')

    {{-- Title --}}
    <title>
        @yield('title', 'YAFFA')
        @hasSection('title_postfix')
            - @yield('title_postfix')
        @endif
    </title>

    @vite('resources/css/app.css')

    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}" />

    {{-- Disable animations during testing --}}
    @if (app()->environment('testing'))
        <style>
        * {
            transition: none !important;
            animation: none !important;
        }
        </style>
    @endif
</head>

<body @yield('classes_body')>
    {{-- The GTM noscript code is ignored even in sandbox mode, and this should not cause any issues --}}

    {{-- Body Content --}}
    @yield('body')

    {{-- Footer Content --}}
    @yield('footer')

    @include('template.components.footer')

    @routes

    <!-- REQUIRED JS SCRIPTS -->
    @vite('resources/js/manifest.js')
    @vite('resources/js/vendor.js')
    @vite('resources/js/app.js')

    @includeWhen(config('yaffa.sandbox_mode'), 'template.sandbox-components.body-close')
</body>
</html>
