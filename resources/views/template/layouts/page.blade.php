@extends('template.master')

@section('body')
<div class="sidebar sidebar-dark sidebar-fixed" id="sidebar">
    <div class="sidebar-brand d-none d-md-flex">
        <div class="sidebar-brand-full">
            <a href="{{ route('home') }}">
                <img src="{{ asset('images/logo-small.png')}}" alt="YAFFA Logo">
                YAFFA
            </a>
        </div>
        <div class="sidebar-brand-narrow">
            <a href="{{ route('home') }}">
                <img src="{{ asset('images/logo-small.png')}}" alt="YAFFA Logo">
            </a>
        </div>
    </div>

    <div class="sidebar-brand sidebar-brand-form d-none d-md-flex">
        <form action="{{ route('search') }}" method="get" class="sidebar-brand-full">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="{{ __('Search...') }}" autocomplete="off">
                <button type="submit" id="search-btn" class="btn btn-outline-secondary"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </form>
    </div>
    <div class="sidebar-brand sidebar-brand-form d-none d-md-flex">
        @if(isset($accountsForNavbar) && count($accountsForNavbar) > 0)
        <form action="#" method="get" class="sidebar-brand-full">
            <div class="input-group">
                <select name="jump_to_account" id="jump_to_account" class="form-select">
                    <option value="">{{ __('Select account to open') }}</option>
                    @foreach($accountsForNavbar as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        @endif
    </div>

    @include('template.components.navigation')

    <button class="sidebar-toggler" type="button" data-coreui-toggle="unfoldable"></button>
</div>

{{-- @include('template.components.right-sidebar') --}}

<div class="wrapper d-flex flex-column min-vh-100 bg-light">
    <header class="header header-sticky mb-4 @env('local') header-local-mode @endenv">
        <div class="container-fluid">
            <button class="header-toggler px-md-0 me-md-3" type="button"
                onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <a class="header-brand d-md-none" href="{{ route('home') }} ">
                <img src="{{ asset('images/logo-small.png')}}" alt="YAFFA Logo">
                YAFFA
            </a>
            <ul class="header-nav ms-auto"></ul>
            <ul class="header-nav ms-3 me-4">
                <li class="nav-item dropdown">
                    <a class="nav-link py-0" data-coreui-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-user me-1"></i>
                        {{ Auth::user()->name }}
                    </a>
                    <div class="dropdown-menu dropdown-menu-end pt-0">
                        <a class="dropdown-item" href="{{ route('user.settings') }}">
                            <i class="fa-solid fa-user me-2"></i>
                            {{ __('My profile') }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <a class="dropdown-item" href="{{ route('logout') }}"
                               onclick="event.preventDefault(); this.closest('form').submit();">
                               <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>
                                {{ __('Logout') }}
                            </a>
                        </form>
                    </div>
                </li>
            </ul>
            {{--
            <button class="header-toggler px-md-0 me-md-3" type="button" onclick="coreui.Sidebar.getInstance(document.querySelector('#aside')).show()">
                <i class="fa fa-bolt"></i>
            </button>
            --}}
        </div>
    </header>
    <div class="body flex-grow-1 px-3">
        <div class="@yield('content_container_classes', 'container-fluid')">
            <h2 class="mb-3">
                @yield('content_header')
            </h2>

            @include('template.components.notifications')

            @yield('content')
        </div>
    </div>
    <footer class="footer">
        <div><a href="https://www.yaffa.cc/" target="_blank">{{ config('app.name') }}</a> {{ config('yaffa.version') }}</div>
        <div class="ms-auto"><a href="https://github.com/kantorge/yaffa" class="text-black"><i class="fa-brands fa-github fa-2x"></i></a></div>
    </footer>
</div>
@stop
