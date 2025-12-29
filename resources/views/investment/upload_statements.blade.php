@extends('template.layouts.page')

@section('title_postfix', __('Upload Investment Statements'))

@section('content')
    <div class="container">
        <h1>{{ __('Upload Investment Statements (WiseAlpha)') }}</h1>

        @if(session('upload_result'))
            <div class="alert alert-success">{{ __('Files queued for processing') }}</div>
        @endif

        <form method="post" action="{{ route('investment.upload_statements.handle') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="account_entity_id">{{ __('Select account') }}</label>
                <select name="account_entity_id" id="account_entity_id" class="form-control" required>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group mt-3">
                <label for="files">{{ __('CSV files') }}</label>
                <input type="file" name="files[]" id="files" multiple accept=".csv,text/csv" class="form-control" required>
            </div>

            <button class="btn btn-primary mt-3">{{ __('Upload & Queue') }}</button>
        </form>
    </div>
@endsection
