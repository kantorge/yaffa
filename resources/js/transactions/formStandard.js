window.transactionData = {};

$( function () {
	//attach transaction type selection events to change visibility of selects
    $("#transaction_type_withdrawal_label").on('click', function() {
        //ignore click if already selected
        if (transactionData.transactionType == $("#transaction_type_withdrawal").val()) {
            return false;
        }
        if (!confirm("Are you sure, you want to change the transaction type? Some data might get lost.")) {
            return false;
        }
    });

    $("#transaction_type_withdrawal").on('change', function() {
		transactionData.transactionType = 'withdrawal';

        //from must be an account
        $('#account_from_label').html("Account from");
        window.transactionData.from.type = 'account';

        //to must be a payee
        $('#account_to_label').html("Payee");
        window.transactionData.to.type = 'payee';

        //TODO: csak akkor változzon, ha ténylegesen megváltozik a típusa
        transactionData.elements.toAccountInput.val(null).trigger('change');
        transactionData.elements.fromAccountInput.val(null).trigger('change');

    });

    $("#transaction_type_deposit_label").click(function(event) {
        //get confirmation if not set by script on first run
        if (transactionData.transactionType == $("#transaction_type_deposit").val()) {
            return false;
        }
        if (!confirm("Are you sure, you want to change the transaction type? Some data might get lost.")) {
            return false;
        }
    });

	$("#transaction_type_deposit").on('change', function(event) {
		transactionData.transactionType = 'deposit';

        //from must be a payee
        $('#account_from_label').html("Payee");
        window.transactionData.from.type = 'payee';

        //to must be an account
        $('#account_to_label').html("Account to");
        window.transactionData.to.type = 'account';

        //TODO: csak akkor változzon, ha ténylegesen megváltozik a típusa
        transactionData.elements.toAccountInput.val(null).trigger('change');
        transactionData.elements.fromAccountInput.val(null).trigger('change');

    });

    $("#transaction_type_transfer_label").on('click', function(event) {
        if ($(this).hasClass('disabled')) {
            return false;
        }

        //get confirmation if not set by script on first run
        if (transactionData.transactionType == $("#transaction_type_transfer").val()) {
            return false;
        }
        if (!confirm("Are you sure, you want to change the transaction type? Some data might get lost.")) {
            return false;
        }
    });

	$("#transaction_type_transfer").on('change', function(){
		transactionData.transactionType = 'transfer';

		//from must be an account
        $('#account_from_label').html("Account from");
        window.transactionData.from.type = 'account';

        //to must be an account
        $('#account_to_label').html("Account to");
        window.transactionData.to.type = 'account';

        //TODO: csak akkor változzon, ha ténylegesen megváltozik a típusa
        transactionData.elements.toAccountInput.val(null).trigger('change');
        transactionData.elements.fromAccountInput.val(null).trigger('change');

		transactionData.updateExchangeRate();

	});
});
