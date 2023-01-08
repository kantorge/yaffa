@extends('template.layouts.page')

@section('title_postfix', __('Dashboard'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Welcome to your YAFFA dashboard!'))

@section('content')
    <div id="app">
        <dashboard></dashboard>
    </div>
@stop
