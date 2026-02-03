@component('mail::message')

Dear {{ $user->name }},

We received your email with subject "{{ $mail->subject }}" and stored it for processing.

Thanks,<br>
{{ config('app.name') }}

@endcomponent
