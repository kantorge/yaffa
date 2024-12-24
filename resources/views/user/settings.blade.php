@extends('template.layouts.page')

@section('title_postfix',  __('My profile'))

@section('content_container_classes', 'container-md')

@section('content_header', __('My profile'))

@section('content')
    <div id="app">
        <my-profile></my-profile>
    </div>
@stop
