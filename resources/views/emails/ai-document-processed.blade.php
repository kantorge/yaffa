@component('mail::message')

@endcomponent

Dear {{ $document->user->name }},

Your document has been successfully processed and is ready for review!

**Document Details:**
- Source: {{ ucfirst(str_replace('_', ' ', $document->source_type)) }}
- Submitted: {{ $document->created_at->format('Y-m-d H:i') }}
- Processed: {{ $document->processed_at->format('Y-m-d H:i') }}

@if(!empty($draftData['raw']))
**Extracted Information:**
- Type: {{ ucfirst($draftData['raw']['transaction_type'] ?? 'N/A') }}
- Amount: {{ $draftData['raw']['amount'] ?? 'N/A' }} {{ $draftData['raw']['currency'] ?? '' }}
- Date: {{ $draftData['date'] ?? 'N/A' }}
- Payee: {{ $draftData['raw']['payee'] ?? 'N/A' }}
@endif

@component('mail::button', ['url' => route('ai-documents.show', $document->id)])
Review Transaction
@endcomponent

Please review the extracted data and finalize the transaction.

Thanks,<br>
{{ config('app.name') }}

@endcomponent
