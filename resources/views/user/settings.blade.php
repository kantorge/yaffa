@extends('template.layouts.page')

@section('title_postfix',  __('User settings'))

@section('content_container_classes', 'container-md')

@section('content_header', __('User settings'))

@section('content')
    <div id="app">
        <my-profile></my-profile>
    </div>
@stop
