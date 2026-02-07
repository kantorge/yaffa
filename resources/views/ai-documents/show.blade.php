@extends('template.layouts.page')

@section('title_postfix', __('AI document'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('AI document'))

@section('content')
    <div id="app">
        <ai-document-viewer></ai-document-viewer>
    </div>
@stop
