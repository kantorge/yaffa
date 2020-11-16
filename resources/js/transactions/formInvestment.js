require( 'daterangepicker');

require('jquery-validation');

math = require("mathjs");

//RRule = require('rrule').RRule;

require('select2');


var transactionData = {
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
	set newCurrencyAccount (x) {
		this.currency.account = x;

		this.updateCurrencies();
	},
	set newCurrencyInvestment (x) {
		this.currency.investment = x;

		this.updateCurrencies();
	},
	updateTotal() {
        var commissionMultiplier = (transactionData.transactionTypeDetails[transactionData.transactionType].amount_operator == 'plus' ? -1 : 1);
		this.data.total = this.data.quantity * this.data.price + this.data.dividend + (commissionMultiplier * this.data.commission);
		$("#transaction_total").val(this.data.total);
	},
	updateCurrencies() {
		$(".transaction_currency").html(this.currency.account);
	}
};

function disableInputWithMath(element) {
	element.prop('disabled', true);
	element.val('');
	transactionData['new' + element.data('control')] = 0;
}
function enableInputWithMath(element) {
	element.prop('disabled', false);
}

$( document ).ready(function() {
	transactionData.elements.quantity = $("#transaction_quantity");
	transactionData.elements.price = $("#transaction_price");
	transactionData.elements.commission = $("#transaction_commission");
	transactionData.elements.dividend = $("#transaction_dividend");

    var elementInvestments = $("#transaction_investment");
    elementInvestments.select2({
		ajax: {
			url: site_url + 'ajax/investments',
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

	elementInvestments.on('select2:select', function (e) {
		$.ajax({
			url: site_url + 'ajax/get_currency_currency_label',
			data: {currency_id: e.params.data.id}
		})
		.done(function( data ) {
			transactionData.newCurrencyInvestment = data;
		});
	});

	elementInvestments.on('select2:unselect', function (e) {
		transactionData.newCurrencyAccount = null;
    });

    //get default value for investment, if it is set
    if (transactionData.investment) {
		$.ajax({
			type: 'GET',
			url: site_url + 'ajax/get_investment_data',
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


    var elementAccounts = $("#transaction_account");
    elementAccounts.select2({
		ajax: {
			url: site_url + 'ajax/accounts',
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
	elementAccounts.on('select2:select', function (e) {
		$.ajax({
			url: site_url + 'ajax/get_account_currency_label',
			data: {account_id: e.params.data.id}
		})
		.done(function( data ) {
			transactionData.newCurrencyAccount = data;
		});
	});

	elementAccounts.on('select2:unselect', function (e) {
		transactionData.newCurrencyAccount = null;
    });

    //get default value for account, if it is set
    if (transactionData.account) {
		$.ajax({
			type: 'GET',
			url: site_url + 'ajax/get_account_data',
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
        transactionData.validator.resetForm();
        transactionData.transactionType = $(this).val();

		if (   $(this).val() == 4
		    || $(this).val() == 5) {
			//buy or sell
			enableInputWithMath(transactionData.elements.quantity);
			enableInputWithMath(transactionData.elements.price);
			enableInputWithMath(transactionData.elements.commission);

			disableInputWithMath(transactionData.elements.dividend);
		} else

		if (   $(this).val() == 8) {
			//dividend
			disableInputWithMath(transactionData.elements.quantity);
			disableInputWithMath(transactionData.elements.price);

			enableInputWithMath(transactionData.elements.commission);
			enableInputWithMath(transactionData.elements.dividend);
		} else

		if (   $(this).val() == 6
		    || $(this).val() == 7) {
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
		var amount = 0;
		try {
			amount = math.eval(this.value.replace(/\s/g,""));
			//console.log('result: ' +amount);
			$(this).closest(".form-group").removeClass("has-error");
			$(this).val	(amount);
		} catch (err) {
			$(this).closest(".form-group").addClass("has-error");
		}

		transactionData['new' + this.dataset.control] = amount || 0;
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
    $("#callback_" + (defaultCallback || 'new')).click();

});
