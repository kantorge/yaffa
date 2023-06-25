@component('mail::message')

    Dear {{ $user->name }},

    The following error occured while processing your email with subject "{{ $mail->subject }}":

    {{ $error }}

    That's all we know. Processing will not be attempted again.

    Thanks,<br>
    {{ config('app.name') }}
@endcomponent
