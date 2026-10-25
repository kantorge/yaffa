@component('mail::message')

{{ __('mail.common.greeting', ['name' => $document->user->name]) }}

{{ __('mail.ai_document_dismissed_non_financial.intro') }}

- {{ __('mail.labels.document_id') }}: #{{ $document->id }}
- {{ __('mail.labels.source_type') }}: {{ __('mail.source_types.' . $document->source_type) }}
- {{ __('mail.labels.submitted') }}: {{ $document->created_at->format('Y-m-d H:i') }}

{{ __('mail.ai_document_dismissed_non_financial.next_action_text') }}

@component('mail::button', ['url' => route('ai-documents.show', $document->id), 'color' => 'success'])
{{ __('mail.ai_document_dismissed_non_financial.button_open_document') }}
@endcomponent

@component('mail::button', ['url' => route('ai-documents.index', ['status' => 'dismissed'])])
{{ __('mail.ai_document_dismissed_non_financial.button_open_dismissed') }}
@endcomponent

{{ __('mail.common.thanks') }}<br>
{{ config('app.name') }}

@endcomponent
