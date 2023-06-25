// Initialize the finalize transaction button
let finalizeTransactionButton = document.querySelector('.finalizeIcon');
if (finalizeTransactionButton) {
    finalizeTransactionButton.addEventListener('click', function () {
        // Navigate to the edit page by submitting a form with the available data
        let form = document.createElement('form');
        form.setAttribute('method', 'POST');
        form.setAttribute('action', window.route('transactions.createFromDraft'));

        // Add csrf token
        let csrfInput = document.createElement('input');
        csrfInput.setAttribute('type', 'hidden');
        csrfInput.setAttribute('name', '_token');
        csrfInput.setAttribute('value', csrfToken);
        form.appendChild(csrfInput);

        // Get the transaction data as JSON from the global mails array and add it to the form
        let input = document.createElement('input');
        input.setAttribute('type', 'hidden');
        input.setAttribute('name', 'transaction');
        input.setAttribute('value', JSON.stringify(window.mail.transaction_data));
        form.appendChild(input);

        // Pass the mail id to the form
        input = document.createElement('input');
        input.setAttribute('type', 'hidden');
        input.setAttribute('name', 'mail_id');
        input.setAttribute('value', window.mail.id);
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    });
}

// Initialize the delete button
document.querySelector('.deleteIcon').addEventListener('click', function () {
    // Confirm the action with the user
    if (!confirm(__('Are you sure to want to delete this item?'))) {
        return;
    }

    // Get the from placed in the DOM
    let form = document.getElementById('form-delete');

    // Adjust the action and submit the form
    form.action = window.route('received-mail.destroy', window.mail.id);
    form.submit();
});
