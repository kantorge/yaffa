@extends('template.master')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('adminlte_css')
    @stack('css')
    @yield('css')
@stop

@section('right-sidebar')
    <div class="p-3 control-sidebar-content">
        <h5>Quick actions</h5>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column " data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('transactions.createStandard') }}">
                            <i class="fa fa-cart-plus"></i>
                            New transaction
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('transactions.createInvestment') }}">
                            <i class="fa fa-chart-line"></i>
                            New investment transaction
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
@stop

@section('classes_body')
    {{ (App::isProduction() ? 'skin-blue' : 'skin-red' ) }}
    sidebar-mini
@endsection

@section('body')
    <div class="wrapper">

        <!-- Main Header -->
        <header class="main-header">

            <!-- Logo -->
            <a href="{{ route('account.summary') }} " class="logo">
                <!-- mini logo for sidebar mini 50x50 pixels -->
                <span class="logo-mini"><b>Y</b></span>
                <!-- logo for regular state and mobile devices -->
                <span class="logo-lg"><b>Y</b>affa</span>
            </a>

            <!-- Header Navbar -->
            <nav class="navbar navbar-static-top" role="navigation">
                <!-- Sidebar toggle button-->
                <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                    <span class="sr-only">Toggle navigation</span>
                </a>
                <!-- Navbar Right Menu -->
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">

                    <!-- Notifications Menu -->
                    <li class="dropdown notifications-menu">
                        <!-- Menu toggle button -->
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <i class="fa fa-bell-o"></i>
                        </a>
                    </li>
                    <!-- User Menu -->
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                        <span class="fa fa-user"></span>
                        <span class="hidden-xs">{{ Auth::user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu">
                        <!-- Menu Footer-->
                        <li class="user-footer">
                            <div class="pull-right">
                                <a class="btn btn-default btn-flat" href="{{ route('logout') }}"
                                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    {{ __('Logout') }}
                                </a>
                            </div>
                        </li>
                        </ul>
                    </li>

                    <!-- Control Sidebar Toggle Button -->
                    <li>
                        <a href="#" data-toggle="control-sidebar"><i class="fa fa-bolt"></i></a>
                    </li>
                    </ul>
                </div>
            </nav>
        </header>

    @include('template.components.left-sidebar')

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <h1>
                @yield('content_header')
            </h1>

        </section>

        <!-- Main content -->
        <section class="content container-fluid">

            @include('template.components.notifications')

            @yield('content')

        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    @include('template.components.right-sidebar')

</div>
<!-- ./wrapper -->

<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf
</form>

@stop

@section('adminlte_js')
    @stack('js')
    @yield('js')
@stop