@extends('template.layouts.page')

@section('title_postfix', 'Import Jobs')

@section('content')
    <div class="container">
        <h1>Import Jobs</h1>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>File</th>
                    <th>Source</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Started</th>
                    <th>Finished</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($imports as $import)
                    <tr>
                        <td>{{ $import->id }}</td>
                        <td>{{ basename($import->file_path) }}</td>
                        <td>{{ $import->source ?? 'generic' }}</td>
                        <td>{{ optional($accounts->get($import->account_entity_id))->name ?? '' }}</td>
                        <td>{{ $import->status }}</td>
                        <td>
                            @if($import->total_rows)
                                {{ $import->processed_rows }} / {{ $import->total_rows }}
                            @else
                                {{ $import->processed_rows }}
                            @endif
                        </td>
                        <td>{{ $import->started_at?->toDateTimeString() }}</td>
                        <td>{{ $import->finished_at?->toDateTimeString() }}</td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('imports.status', $import->id) }}">Status</a>
                            @if($import->errors)
                                <a class="btn btn-sm btn-outline-danger" href="{{ route('imports.errors', $import->id) }}">Errors</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($imports->hasPages())
        
        @endif
    </div>
@endsection
