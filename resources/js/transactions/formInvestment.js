require( 'daterangepicker');

require('jquery-validation');

math = require("mathjs");

//RRule = require('rrule').RRule;

require('select2');

window.transactionData = {
	elements : {},

	data : {
		quantity : 0,
		price : 0,
		commission : 0,
		dividend : 0,
		total : 0
	},
	currency : {
		account : null,
		investment : null
	},

	set newQuantity (x) {
		this.data.quantity = x;

		this.updateTotal();
	},
	set newPrice (x) {
		this.data.price = x;

		this.updateTotal();
	},
	set newCommission (x) {
		this.data.commission = x;

		this.updateTotal();
	},
	set newDividend (x) {
		this.data.dividend = x;

		this.updateTotal();
	},
};

window.updateTotal = function() {
	//var commissionMultiplier = (transactionData.transactionTypeDetails[transactionData.transactionType].amount_operator == 'plus' ? -1 : 1);
	//this.data.total = this.data.quantity * this.data.price + this.data.dividend + (commissionMultiplier * this.data.commission);
	$("#transaction_total").val(this.data.total);
}

window.updateCurrencies = function() {
	$(".transaction_currency").html(transactionData.elements.accounts.data('currency'));
}

function disableInputWithMath(element) {
	element.prop('disabled', true);
	element.val('');
	transactionData['new' + element.data('control')] = 0;
}
function enableInputWithMath(element) {
	element.prop('disabled', false);
}

$( function () {
	transactionData.elements.quantity = $("#transaction_quantity");
	transactionData.elements.price = $("#transaction_price");
	transactionData.elements.commission = $("#transaction_commission");
	transactionData.elements.dividend = $("#transaction_dividend");
	transactionData.elements.investments = $("#transaction_investment");
	transactionData.elements.accounts = $("#transaction_account");

    transactionData.elements.investments.select2({
		ajax: {
			url: '/api/assets/investment',
			dataType: 'json',
			delay: 150,
			processResults: function (data) {
				return {
					results: data
				};
			},
			cache: true
		},
		selectOnClose: true,
		placeholder: "Select investment",
		allowClear: true
	});

	transactionData.elements.investments.on('select2:select', function (e) {
		$.ajax({
			url: '/assets/investment',
			data: {currency_id: e.params.data.id}
		})
		.done(function( data ) {
			transactionData.elements.investments.data('currency', data);
			window.updateCurrencies();
		});
	});

	transactionData.elements.investments.on('select2:unselect', function (e) {
		transactionData.elements.investments.data('currency', null);
		window.updateCurrencies();
    });

    //get default value for investment, if it is set
    if (transactionData.investment) {
		$.ajax({
			type: 'GET',
			url: '/assets/investment',
			dataType: 'json',
			data: {
				id: transactionData.investment
			}
		}).then(function (data) {
			// create the option and append to Select2
			var option = new Option(data.name, data.id, true, true);
			elementInvestments.append(option).trigger('change');

			// manually trigger the `select2:select` event
			elementInvestments.trigger({
				type: 'select2:select',
				params: {
					data: data
				}
			});
		});
	}

    transactionData.elements.accounts.select2({
		ajax: {
			url: '/api/assets/account',
			dataType: 'json',
			delay: 150,
			processResults: function (data) {
				return {
					results: data
				};
			},
			cache: true
		},
		selectOnClose: true,
		placeholder: "Select account",
		allowClear: true
	});

	transactionData.elements.accounts.on('select2:select', function (e) {
		$.ajax({
			url: '/api/assets/get_account_currency',
			data: {account_id: e.params.data.id}
		})
		.done(function( data ) {
			transactionData.elements.accounts.data('currency', data);
			window.updateCurrencies();
		});
	});

	transactionData.elements.accounts.on('select2:unselect', function (e) {
		transactionData.elements.accounts.data('currency', null);
		window.updateCurrencies();
    });

    //get default value for account, if it is set
    if (transactionData.account) {
		$.ajax({
			type: 'GET',
			url: 'ajax/get_account_data',
			dataType: 'json',
			data: {
				id: transactionData.account
			}
		}).then(function (data) {
			// create the option and append to Select2
			var option = new Option(data.name, data.id, true, true);
			elementAccounts.append(option).trigger('change');

			// manually trigger the `select2:select` event
			elementAccounts.trigger({
				type: 'select2:select',
				params: {
					data: data
				}
			});
		});
	}

	$("#transaction_type").change(function() {
		//console.log($(this).val());
        //transactionData.validator.resetForm();
        transactionData.transactionType = $(this).val();

		if (   $(this).val() == 'Buy'
		    || $(this).val() == 'Sell') {
			enableInputWithMath(transactionData.elements.quantity);
			enableInputWithMath(transactionData.elements.price);
			enableInputWithMath(transactionData.elements.commission);

			disableInputWithMath(transactionData.elements.dividend);
		} else

		if (   $(this).val() == 'Dividend'
			|| $(this).val() == 'S-Term Cap Gains Dist'
			|| $(this).val() == 'L-Term Cap Gains Dist') {
			//dividend
			disableInputWithMath(transactionData.elements.quantity);
			disableInputWithMath(transactionData.elements.price);

			enableInputWithMath(transactionData.elements.commission);
			enableInputWithMath(transactionData.elements.dividend);
		} else

		if (   $(this).val() == 'Add shares'
		    || $(this).val() == 'Remove shares') {
			//add or remove
			enableInputWithMath(transactionData.elements.quantity);
			disableInputWithMath(transactionData.elements.price);
			enableInputWithMath(transactionData.elements.commission);
			disableInputWithMath(transactionData.elements.dividend);
		}

	});

	$(".input-with-math").on('blur', function() {
		/*
			Handle changes to numerical inputs.
			Parse input. Display error, if NaN. Update total.
		*/

		processNumericInput(this);
		window.updateTotal();
	});

    //datepicker
	$('#transaction_date').daterangepicker(datePickerStandardSettings);

    //form validation
    /*
	transactionData.validator = $("#formTransaction").validate({
		//debug: true,
		ignore: '.ignore, :hidden',
		rules: {
			date: {
				required: function() {
                    return ($("#entry_type_schedule").is(':not(:checked)'));
                },
				dateISO: true
			},
			account: {
				required: {}
			},
			investment: {
                required: {},
                sameCurrencies: {}
			},
			quantity: {
				required: {}
			},
			price: {
				required: {}
			},
			dividend: {
				required: {}
            },
            //schedule
            schedule_start: {
				required: function() {
					return ($("#entry_type_schedule").is(':checked'));
				},
				dateISO: true
            },
            schedule_end: {
				dateISO: true
            },
            schedule_interval: {
                number: true,
                minStrict: 0
            },
            schedule_count: {
                number: true,
                minStrict: 0
            },
            schedule_frequency: {
                required: function() {
					return ($("#entry_type_schedule").is(':checked'));
				}
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

	//custom functions for validator
	$.validator.addMethod('sameCurrencies', function (value, el, param) {
		$.validator.messages.sameCurrencies = 'Accound and investment currencies must be the same.';
		return transactionData.currency.account == transactionData.currency.investment;
    });

	$.validator.addMethod('minStrict', function (value, el, param) {
		if (this.optional(el)) {  // "required" not in force and field is empty
			return true;
		}
		$.validator.messages.minStrict = 'Must be greather than zero';
		return value > param;
    });
    */

    //hide schedule box if not schedule
    //TODO: can this be in blade template?
    if (! $("#entry_type_schedule").prop( "checked" )) {
        $("#schedule_container").hide();
    } else {
        $("#transaction_date").prop( "disabled", true ).prop( "value", "");
    }

    //adjust inputs based on schedule
    $("#entry_type_schedule").click(function(e) {
        var isSchedule = $("#entry_type_schedule").prop( "checked" );

        if (isSchedule) {
            $("#schedule_container").show();
            $("#transaction_date").prop( "disabled", true ).prop( "value", "");
        } else {
            $("#schedule_container").hide();
            $("#transaction_date").prop( "disabled", false );
        }
    });

	//initial transaction type
    $("#transaction_type").change();

	//set callback type
	var defaultCallback = defaultCallback || 'new';
	document.getElementById("callback_" + defaultCallback).click();

	//display fixed footer
    setTimeout(function() {
        $("footer").removeClass("hidden");
    }, 1000);
});
