@extends('template.layouts.page')

@section('title', __('MoneyHub Transaction Upload'))

@section('content_header', __('MoneyHub Transaction Upload'))



@section('content')
<div class="container py-3">
    <div id="moneyhub-upload-app">
        <money-hub-upload-tool></money-hub-upload-tool>
    </div>
</div>
@endsection
