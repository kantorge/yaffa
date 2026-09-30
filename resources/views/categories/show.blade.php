@extends('template.layouts.page')

@section('title_postfix', __('Category details'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Category details') }} - {{ $category->full_name }}
@stop

@section('content')
    {{-- Vue page island is added in the frontend step; data is available as window.category, window.overview, window.budgets --}}
    <div id="categoryShow"></div>
@stop
