@component('mail::message')

{{ $body }}

Thanks,<br>
{{ config('app.name') }}

@endcomponent
