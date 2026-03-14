@extends('template.layouts.page')

@section('title_postfix', __('AI document settings'))

@section('content_container_classes', 'container-md')

@section('content_header', __('AI document settings'))

@section('content')
    <div id="app">
        <ai-settings></ai-settings>
    </div>
@stop