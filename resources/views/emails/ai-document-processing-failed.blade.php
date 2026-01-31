@component('mail::message')

Dear {{ $document->user->name }},

The document processing failed with the following error:

{{ $error }}

Document Details:
- Source: {{ $document->source_type }}
- Submitted: {{ $document->created_at->format('Y-m-d H:i') }}

You can try reprocessing this document or contact support if the problem persists.

Thanks,<br>
{{ config('app.name') }}

@endcomponent
