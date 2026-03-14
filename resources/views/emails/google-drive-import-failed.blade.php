@component('mail::message')

{{ __('mail.common.greeting', ['name' => $user->name]) }}

{{ __('mail.google_drive_import_failed.intro') }}

**{{ __('mail.labels.reason') }}**
{{ __('mail.google_drive_import_failed.error', ['error' => $error]) }}

**{{ __('mail.ai_document_processing_failed.document_details') }}**
- {{ __('mail.google_drive_import_failed.folder', ['folder' => $config->folder_id]) }}
- {{ __('mail.google_drive_import_failed.error_count', ['count' => (int) $config->error_count]) }}

@component('mail::button', ['url' => route('user.ai-settings')])
{{ __('mail.google_drive_import_failed.button_open_settings') }}
@endcomponent

{{ __('mail.common.thanks') }}<br>
{{ config('app.name') }}

@endcomponent
