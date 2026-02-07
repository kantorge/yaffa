@extends('template.layouts.page')

@section('title_postfix', __('AI documents'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('AI documents'))

@section('content')
    <div id="app">
        <ai-document-manager></ai-document-manager>
    </div>
@stop
