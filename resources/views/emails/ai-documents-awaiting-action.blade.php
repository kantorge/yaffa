@component('mail::message')

{{ __('mail.common.greeting', ['name' => $user->name]) }}

{{ __('mail.ai_documents_awaiting_action.intro', ['count' => $count, 'days' => $retentionDays]) }}

{{ __('mail.ai_documents_awaiting_action.next_action_text') }}

@component('mail::button', ['url' => $url])
{{ __('mail.ai_documents_awaiting_action.button_review_documents') }}
@endcomponent

{{ __('mail.common.thanks') }}<br>
{{ config('app.name') }}

@endcomponent
