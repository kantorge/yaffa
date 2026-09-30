@extends('template.layouts.page')

@section('title_postfix', __('Category details'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Category details') }} - {{ $category->full_name }}
@stop

@section('content')
    {{-- Data is passed to the Vue island via window.category, overview, budgets, learningEntries, baseCurrency --}}
    <div id="categoryShow"></div>
@stop
