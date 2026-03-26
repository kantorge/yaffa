@component('mail::message')

{{ __('mail.common.greeting', ['name' => $document->user->name]) }}

{{ __('mail.ai_document_processing_failed.intro') }}

**{{ __('mail.labels.reason') }}**
{{ $error ?: __('mail.ai_document_processing_failed.fallback_reason') }}

**{{ __('mail.ai_document_processing_failed.document_details') }}**
- {{ __('mail.labels.document_id') }}: #{{ $document->id }}
- {{ __('mail.labels.source_type') }}: {{ __('mail.source_types.' . $document->source_type) }}
- {{ __('mail.labels.submitted') }}: {{ $document->created_at->format('Y-m-d H:i') }}

**{{ __('mail.ai_document_processing_failed.next_action_title') }}**
{{ __('mail.ai_document_processing_failed.next_action_text') }}

@component('mail::button', ['url' => route('ai-documents.show', $document->id), 'color' => 'error'])
{{ __('mail.ai_document_processing_failed.button_review_reprocess') }}
@endcomponent

{{ __('mail.ai_document_processing_failed.settings_hint') }}

@component('mail::button', ['url' => route('user.ai-settings')])
{{ __('mail.ai_document_processing_failed.button_open_settings') }}
@endcomponent

{{ __('mail.common.thanks') }}<br>
{{ config('app.name') }}

@endcomponent
