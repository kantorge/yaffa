@extends('template.layouts.page')

@section('title', __('User settings'))

@section('content_header')
    {{ __('User settings') }}
@stop

@section('content')


    <form
        accept-charset="UTF-8"
        action="{{ route('user.update') }}"
        autocomplete="off"
        method="POST"
    >
    <input name="_method" type="hidden" value="PATCH">

    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">
                {{ __('Update user settings') }}
            </h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body form-horizontal">
            <div class="form-group">
                <label for="name" class="control-label col-sm-3">
                    {{ __('Language') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="language"
                        name="language"
                    >
                        @foreach($languages as $code => $language)
                            <option
                                value="{{ $code }}"
                                @if (old() && old('language') == $code)
                                    selected="selected"
                                @elseif(Auth::user()->language === $code)
                                    selected="selected"
                                @endif
                            >
                                {{ $language }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="name" class="control-label col-sm-3">
                    {{ __('Locale') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="locale"
                        name="locale"
                    >
                        @foreach($locales as $code => $language)
                            <option
                                value="{{ $code }}"
                                @if (old() && old('locale') == $code)
                                    selected="selected"
                                @elseif(Auth::user()->locale === $code)
                                    selected="selected"
                                @endif
                            >
                                {{ $language }}
                            </option>
                    @endforeach
                    </select>
                </div>
            </div>
        </div>
        <!-- /.box-body -->
        <div class="box-footer">
            @csrf

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
        </div>
        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

    </form>

@stop
