@component('mail::message')

{{ __('mail.common.greeting', ['name' => $user->name]) }}

{{ __('mail.google_drive_import_success.intro') }}

**{{ __('mail.ai_document_processed.what_happened') }}**
- {{ __('mail.google_drive_import_success.folder', ['folder' => $config->folder_name ?? $config->folder_id]) }} ({{ $config->folder_id }})
- {{ __('mail.google_drive_import_success.imported', ['count' => $stats['imported']]) }}
- {{ __('mail.google_drive_import_success.skipped_existing', ['count' => $stats['skipped_existing']]) }}
- {{ __('mail.google_drive_import_success.skipped_unsupported', ['count' => $stats['skipped_unsupported']]) }}
- {{ __('mail.google_drive_import_success.skipped_too_large', ['count' => $stats['skipped_too_large']]) }}
- {{ __('mail.google_drive_import_success.failed_downloads', ['count' => $stats['failed_downloads']]) }}

@if(!empty($stats['disposition_failures']))
**{{ __('mail.google_drive_import_success.disposition_warning') }}**
@foreach($stats['disposition_failures'] as $failure)
- {{ $failure['file'] }}
@endforeach
{{ __('mail.google_drive_import_success.disposition_warning_detail') }}
@endif

@component('mail::button', ['url' => route('ai-documents.index')])
{{ __('mail.google_drive_import_success.button_open_documents') }}
@endcomponent

{{ __('mail.common.thanks') }}<br>
{{ config('app.name') }}

@endcomponent
