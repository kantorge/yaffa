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

// Initialize the reset processed button
document.querySelector('.reprocessIcon').addEventListener('click', function () {
    // Confirm the action with the user
    if (!confirm(__('Are you sure to want to reprocess this email? Current data will be overwritten.'))) {
        return;
    }

    // Prevent running multiple times in parallel
    if ($(this).hasClass("busy")) {
        return false;
    }

    $(this).addClass('busy');

    const id = window.mail.id;

    axios.patch(window.route('api.received-mail.reset-processed', {'receivedMail': id}))
        .then(function (response) {
            // Reload the page
            location.reload();

            // TODO: handle update of the page without reloading
        })
        .catch(function (error) {
            // Emit a custom event to global scope about the result
            let notificationEvent = new CustomEvent('notification', {
                detail: {
                    notification: {
                        type: 'danger',
                        message: 'Error reseting email processed status (#' + id + '): ' + error,
                        title: null,
                        icon: null,
                        dismissible: true,
                    }
                },
            });
            window.dispatchEvent(notificationEvent);

            $(selector).find(".busy[data-delete]").removeClass('busy')
        });

});
