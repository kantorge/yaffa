<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @if(config('yaffa.gtm_container_id') && preg_match( '/^GTM-[A-Z0-9]+/', config('yaffa.gtm_container_id') ))
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ config('yaffa.gtm_container_id') }}');</script>
        <!-- End Google Tag Manager -->
    @endif

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

    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
    <link rel="stylesheet" href="{{ mix('css/vendor.css') }}">

    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}" />

</head>

<body @yield('classes_body')>
    @if(config('yaffa.gtm_container_id') && preg_match( '/^GTM-[A-Z0-9]+/', config('yaffa.gtm_container_id') ))
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('yaffa.gtm_container_id') }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
    @endif

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
