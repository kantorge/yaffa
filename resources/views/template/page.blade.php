@extends('template.master')

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

@section('classes_body', '')

@section('body')
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

            @include('notfications')

            @yield('content')

        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

@stop

@section('adminlte_js')
    @stack('js')
    @yield('js')
@stop
