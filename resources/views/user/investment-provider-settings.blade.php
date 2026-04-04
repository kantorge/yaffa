@extends('template.layouts.page')

@section('title_postfix', __('Investment provider settings'))

@section('content_container_classes', 'container-md')

@section('content_header', __('Investment provider settings'))

@section('content')
    <div id="app">
        <investment-provider-settings></investment-provider-settings>
    </div>
@stop