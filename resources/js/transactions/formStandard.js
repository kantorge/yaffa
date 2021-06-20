require('jquery-validation');
//require("jquery-validation/dist/additional-methods.js");

window.transactionData = {
    resetAccount(type) {
        this[type].account_id = null;
        this[type].account_currency = null;
        if (this[type].type == 'payee') {
            this.payeeCategory.id = null;
            this.payeeCategory.text = null;
        }
    },
};

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



    //Set up form validation
	window.validation = $("#formTransaction").validate({
		ignore: '.ignore, :hidden',
		rules: {
            transaction_type: {
                required: true
            },
			date: {
				required: function() {
                        return (   $("#entry_type_schedule").is(':not(:checked)')
                                || $("#entry_type_budget").is(':not(:checked)'));
                    },
				dateISO: true
			},
			"config[account_from_id]": {
                //TODO: can it be, and should it be checked, if type is correct according to transaction type
                required: true
			},
			"config[account_to_id]": {
                //TODO: can it be, and should it be checked, if type is correct according to transaction type
                required: true
			},
			"config[amount_from]": {
				required: true,
				minStrict: 0,
				number: true
			},
			"config[amount_to]": {
				required: true,
				minStrict: 0,
				number: true
            },

            //TODO: validate visible items

            //schedule
            //requirement is handled by having them hidden, if not needed
            schedule_start: {
                required: true,
				dateISO: true,
            },
            schedule_next: {
                //TODO: after or equal to start date
                //TODO: before end date
                required: true,
				dateISO: true,
            },
            schedule_end: {
                required: true,
                dateISO: true,
                //TODO: after start date
            },
            schedule_interval: {
                required: true,
                number: true,
                minStrict: 0
            },
            schedule_count: {
                number: true,
                minStrict: 0
            },
            schedule_frequency: {
                required: true,
                //TODO: validate values
            }
		},
        highlight: function(element, errorClass, validClass) {
            $(element).parent('div').addClass(errorClass).removeClass(validClass);
        },
        unhighlight: function(element, errorClass, validClass) {
            $(element).parent('div').addClass(validClass).removeClass(errorClass);
        },
		errorClass: 'has-error'
    });

});

//custom functions for validator
$.validator.addMethod('minStrict', function (value, el, param) {
    if (this.optional(el)) {  // "required" not in force and field is empty
        return true;
    }
    $.validator.messages.minStrict = 'Must be greather than zero';
    return value > param;
});
