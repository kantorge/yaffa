@extends('template.layouts.page')

@section('title_postfix', __('Upload Payslips'))

@section('content')
    <div class="container">
        <h1>{{ __('Upload Payslips') }}</h1>

        <p class="text-muted">
            Upload JSON payslip files processed by your unstructured data processor. Each file will be imported as a deposit transaction with categorized earnings, deductions, and taxes.
        </p>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('payslip.upload.handle') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
                <label for="account_entity_id" class="form-label">{{ __('Employment Account') }} <span class="text-danger">*</span></label>
                <select name="account_entity_id" id="account_entity_id" class="form-select @error('account_entity_id') is-invalid @enderror" required>
                    <option value="">{{ __('Select an employment account...') }}</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ old('account_entity_id') == $account->id ? 'selected' : '' }}>
                            {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                @error('account_entity_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @if($accounts->isEmpty())
                    <div class="form-text text-warning">
                        No employment accounts found. Please create an account with category "employment" first.
                    </div>
                @endif
            </div>

            <div class="mb-3">
                <label for="files" class="form-label">{{ __('Payslip JSON Files') }} <span class="text-danger">*</span></label>
                <input type="file" 
                       name="files[]" 
                       id="files" 
                       class="form-control @error('files.*') is-invalid @enderror" 
                       accept=".json"
                       multiple
                       required>
                @error('files.*')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Select one or more JSON files. Supported format: Unstructured.io payslip output with HTML tables.
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" {{ $accounts->isEmpty() ? 'disabled' : '' }}>
                    <i class="fa fa-upload"></i> {{ __('Upload Payslips') }}
                </button>
                <a href="{{ route('imports.index') }}" class="btn btn-outline-secondary">
                    <i class="fa fa-list"></i> {{ __('View Imports') }}
                </a>
            </div>
        </form>
    </div>
@endsection
