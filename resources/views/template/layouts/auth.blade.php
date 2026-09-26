@extends('template.master')

@section('body')

    @include('template.components.notifications')

    @yield('content')

@endsection
