@extends('template.layouts.page')

@section('title_postfix', __('Upload Investments CSV'))
@section('content_header', __('Upload Investments CSV'))
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Upload CSV</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('investment.upload_csv') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">CSV File</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </form>
                    @if(session('upload_result'))
                        <div class="alert alert-info mt-3">
                            <strong>Upload Result:</strong>
                            <pre>{{ print_r(session('upload_result'), true) }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
