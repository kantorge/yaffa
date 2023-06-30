@component('mail::message')

    Dear {{ $user->name }},

    From the email you have sent us, we have created a draft transaction for you.

    Transaction type: {{ $transaction['transaction_type']['name'] }}
    Date: {{ $transaction['date'] }}
    Account: {{ $transaction['raw']['account'] }}
    Payee: {{ $transaction['raw']['payee'] }}
    Amount: {{ $transaction['raw']['amount'] }}

    You can edit and finalize the transaction by clicking the button below.

    @component('mail::button', [
        'url' => route('received-mail.show', ['received_mail' =>$mail->id])
    ])
        Edit transaction
    @endcomponent

    Thanks,<br>
    {{ config('app.name') }}
@endcomponent
