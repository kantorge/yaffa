@component('mail::message')

{{ __('mail.ai_document_processed.greeting', ['name' => $document->user->name]) }}

{{ __('mail.ai_document_processed.intro') }}

**{{ __('mail.ai_document_processed.what_happened') }}**
- {{ __('mail.labels.document_id') }}: #{{ $document->id }}
- {{ __('mail.labels.source_type') }}: {{ __('mail.source_types.' . $document->source_type) }}
- {{ __('mail.labels.submitted') }}: {{ $document->created_at->format('Y-m-d H:i') }}
- {{ __('mail.labels.processed') }}: {{ optional($document->processed_at)->format('Y-m-d H:i') ?? __('mail.common.na') }}

@if(!empty($draftData['raw']))
**{{ __('mail.ai_document_processed.extracted_summary') }}**
- {{ __('mail.labels.type') }}: {{ !empty($draftData['raw']['transaction_type']) ? __('mail.transaction_types.' . $draftData['raw']['transaction_type']) : __('mail.common.na') }}
- {{ __('mail.labels.amount') }}: {{ $draftData['raw']['amount'] ?? __('mail.common.na') }} {{ $draftData['raw']['currency'] ?? '' }}
- {{ __('mail.labels.date') }}: {{ $draftData['date'] ?? __('mail.common.na') }}
@endif

**{{ __('mail.ai_document_processed.next_action_title') }}**
{{ __('mail.ai_document_processed.next_action_text') }}

@component('mail::button', ['url' => route('ai-documents.show', $document->id), 'color' => 'success'])
{{ __('mail.ai_document_processed.button_review_document') }}
@endcomponent

@component('mail::button', ['url' => route('ai-documents.index')])
{{ __('mail.ai_document_processed.button_open_documents') }}
@endcomponent

{{ __('mail.common.thanks') }}<br>
{{ config('app.name') }}

@endcomponent
