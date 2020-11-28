require( 'daterangepicker');

require('jquery-validation');

math = require("mathjs");

//RRule = require('rrule').RRule;

require('select2');

window.transactionData = {
    //store various element references
	elements : {},

    //counter to ensure that item rows always get a new id
	itemRowCounter : document.getElementById("transaction_item_container").querySelectorAll(".transaction_item_row").length,

    from: {
        type: 'account',
        account_id : 0,
        amount : 0,
        account_currency : null,

    },

    to: {
        type: 'payee',
        account_id : 0,
        amount : 0,
        account_currency : null,
    },

    payeeCategory : {
        id: null,
        text: null
    },

    getApiUrl(type) {
        return (this[type].type == 'account' ? '/api/assets/account' : '/api/assets/payee' );
    },
    getApiType: function(type) {
        return this[type].type;
    },
    resetAccount(type) {
        this[type].account_id = null;
        this[type].account_currency = null;
        if (this[type].type == 'payee') {
            this.payeeCategory.id = null;
            this.payeeCategory.text = null;
        }
    },
    getPayeeData() {
        if (this.to.type == 'payee') {
            return this.to.account_id;
        }

        if (this.from.type == 'payee') {
            return this.from.account_id;
        }

        return null;
    },

	itemTotal: 0,
	remainingAmountToPayeeDefault : 0,
    remainingAmountNotAllocated: 0,

	setPayeeCategory(data) {
		if (data) {
			this.payeeCategory.id = data.id;
			this.payeeCategory.text = data.full_name;
		} else {
			this.payeeCategory.id = null;
			this.payeeCategory.text = null;
		}

		$("#payee_category_name").html((this.payeeCategory.id ? "</br>(" + this.payeeCategory.text + ")" : ""));
		this.updateTotals();
    },

	updateTotals() {
		//get all amounts for items
		var total_amount = 0;

		$("div.transaction_item_row .transaction_item_amount").each(function() {
			try {
				var current_amount = math.evaluate(this.value.replace(/\s/g,""));
			} catch(err) {
				var current_amount = 0;
			}
			if (!isNaN(current_amount)) {
				total_amount += current_amount;
			}
		});
		this.itemTotal = total_amount;

        //calculate remaining value
		if (this.isPayeePresent()) {
            //default payee available
			this.remainingAmountToPayeeDefault = math.subtract(math.bignumber(this.from.amount), math.bignumber(this.itemTotal)).toNumber();
            this.remainingAmountNotAllocated = 0;
            $("#remaining_payee_default_container").show();
            $("#remaining_not_allocated_container").hide();
		} else {
            //default payee NOT available
			this.remainingAmountNotAllocated = math.subtract(math.bignumber(this.from.amount), math.bignumber(this.itemTotal)).toNumber();
            this.remainingAmountToPayeeDefault = 0;
            $("#remaining_payee_default_container").hide();
            $("#remaining_not_allocated_container").show();
		}

		//display and distribute results
		$("#transaction_item_total").html(this.itemTotal);
		$("#remaining_payee_default").html(this.remainingAmountToPayeeDefault);
		$("#remaining_payee_default_input").val(this.remainingAmountToPayeeDefault);
		$("#remaining_not_allocated").html(this.remainingAmountNotAllocated);
		$("#remaining_not_allocated_input").val(this.remainingAmountNotAllocated);

        //update remaining copy buttons
        $(".transaction_item_row button.load_remainder").prop('disabled', this.remainingAmountNotAllocated <= 0 && this.remainingAmountToPayeeDefault <= 0);

		//update warning states
	},

	isToCurrencyPresent() {
		return (   this.from.currency !== this.to.currency
				&& this.to.currency);
    },

    isPayeePresent() {
        if (this.from.type !== 'payee' && this.to.type !== 'payee') {
            return false;
        };

        return (this.payeeCategory.id !== null);
    },

    getpayeeType() {
        if (this.from.type == 'payee') {
            return 'from';
        }

        if (this.to.type == 'payee') {
            return 'to';
        }

        return false;
    },

	/**
	 * check if amount from should be visible, and take care of visibility
	 * field set is visible, if
	 * - transaction type is transfer
	 * - both accounts are set
	 * - currency of accounts is different
	 */
	updateExchangeRate() {
        /*
        TODO: is this needed?
		//prevent running before having elements set
		if (!Object.keys(this.elements).length) {
			return false;
        }
        */

		if (this.isToCurrencyPresent()) {
			if (this.from.amount !== 0 && this.to.amount !== 0) {
				$("#transfer_exchange_rate").html((this.to.amount / this.from.amount).toFixed(4));
				$('#transfer_exchange_rate_group').show();
			}

			transactionData.elements.toAmountGroup.show();
		} else {
			$("#transfer_exchange_rate").html();
			$('#transfer_exchange_rate_group').hide();
			transactionData.elements.toAmountGroup.hide();
		}
	},

	/**
	 * update currency labels accross the form
	 *
	 * @returns false on failure, true on success
	 * (not used currently)
	 */
	updateCurrencies() {
		//prevent running before having target elements set
		if (!Object.keys(this.elements).length) {
			return false;
		}

		$(".transaction_currency_from").html(
			(this.from.currency
			? "(" + this.from.currency + ")"
			: "")
		);
		$(".transaction_currency_to").html(
			(this.to.currency
			? "(" + this.to.currency + ")"
			: "")
		);

		$(".transaction_currency_from_nowrap").html(this.from.currency);

		return true;
	}

};

$( function () {
    //merge existing data into data template
    window.transactionData = Object.assign(window.baseTransactionData, window.transactionData);

	//assign various key elements to transaction variable
    transactionData.elements.toAccountInput = $("#account_to");
    transactionData.elements.fromAccountInput = $("#account_from");
	transactionData.elements.toAmountGroup = $("#amount_to_group");
	transactionData.elements.toAmountInput = $("#transaction_amount_to");
	transactionData.elements.fromAmountGroup = $("#amount_from_group");
	transactionData.elements.fromAmountInput = $("#transaction_amount_from");

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

        //no currency exchange is expected, hide relevant display
		$('#transfer_exchange_rate_group').hide();
		$(".transaction_currency_to").html("");

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

        //no currency exchange is expected, hide relevant display
		$('#transfer_exchange_rate_group').hide();
		$(".transaction_currency_to").html("");

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

    //account FROM dropdown functionality
	transactionData.elements.fromAccountInput.select2({
		ajax: {
			url: function() {
                return window.transactionData.getApiUrl('from');
            },
			dataType: 'json',
            delay: 150,
            data: function (params) {
                var queryParameters = {
                  q: params.term,
                  transaction_type: transactionData.transactionType,
                  account_type: 'from'
                }

                return queryParameters;
            },
			processResults: function (data) {
				//exclude current selection from results
				var other = transactionData.elements.toAccountInput.get(0);
				var other_id = (other.selectedIndex === -1 ? -1 : other.options[other.selectedIndex].value);

				return {
					results: data.filter(function(obj) {return obj.id !== other_id;})
				};
			},
			cache: true
		},
        selectOnClose: true,
        //TODO: make dynamic
		placeholder: "Select account to debit",
        allowClear: true
    });

    transactionData.elements.fromAccountInput.on('select2:select', function (e) {
        transactionData.from.account_id = e.params.data.id;

        if (transactionData.getApiType('from') == 'account') {
            $.ajax({
                url:  '/api/assets/get_account_currency',
                data: {account_id: e.params.data.id}
            })
            .done(function( data ) {
                transactionData.from.currency = data;

                transactionData.updateCurrencies();
                transactionData.updateExchangeRate();
            });
        } else {
            $.ajax({
                url:  '/api/assets/get_default_category_for_payee',
                data: {payee_id: e.params.data.id}
            })
            .done(function( data ) {
                transactionData.setPayeeCategory(data);
            });
        }
	});

	transactionData.elements.fromAccountInput.on('select2:unselect', function (e) {
        transactionData.resetAccount('from');
	});

    //get default value for master account
    /*
	if (transactionData.accountMaster) {
		$.ajax({
			type: 'GET',
			url:  '/ajax/get_account_data',
			dataType: 'json',
			data: {
				id: transactionData.accountMaster
			}
		}).then(function (data) {
			// create the option and append to Select2
			var option = new Option(data.name, data.id, true, true);
			transactionData.elements.masterAccountInput.append(option).trigger('change');

			// manually trigger the `select2:select` event
			transactionData.elements.masterAccountInput.trigger({
				type: 'select2:select',
				params: {
					data: data
				}
			});
		});
    }
    */

    //account TO dropdown functionality
    transactionData.elements.toAccountInput.select2({
        ajax: {
            url: function() {
                return window.transactionData.getApiUrl('to');
            },
            dataType: 'json',
            delay: 150,
            data: function (params) {
                var queryParameters = {
                    q: params.term,
                    transaction_type: transactionData.transactionType,
                    account_type: 'to'
                }

                return queryParameters;
            },
            processResults: function (data) {
                //exclude current selection from result list
                var other = transactionData.elements.fromAccountInput.get(0);
                var other_id = (other.selectedIndex === -1 ? -1 : other.options[other.selectedIndex].value);

                return {
                    results: data.filter(function(obj) {return obj.id != other_id;})
                };
            },
            cache: true
        },
        selectOnClose: true,
        //TODO: make dynamic
        placeholder: "Select account to credit",
        allowClear: true
    });


    transactionData.elements.toAccountInput.on('select2:select', function (e) {
        transactionData.to.account_id = e.params.data.id;

        if (transactionData.getApiType('to') == 'account') {

            $.ajax({
                url:  '/api/assets/get_account_currency',
                data: {account_id: e.params.data.id}
            })
            .done(function( data ) {
                transactionData.to.currency = data;

                transactionData.updateCurrencies();
                transactionData.updateExchangeRate();
            });
        } else {
            $.ajax({
                url:  '/api/assets/get_default_category_for_payee',
                data: {payee_id: e.params.data.id}
            })
            .done(function( data ) {
                transactionData.setPayeeCategory(data);
            });
        }
    });

    transactionData.elements.toAccountInput.on('select2:unselect', function (e) {
        transactionData.resetAccount('to');

        transactionData.updateCurrencies();
        transactionData.updateExchangeRate();
    });

    $('#transaction_date').daterangepicker(datePickerStandardSettings);


  //set callback type
  var defaultCallback = defaultCallback || 'new';
  $("#callback_" + defaultCallback ).click();

  //adjust inputs based on schedule AND budget selection
  $("#entry_type_schedule, #entry_type_budget").click(function(e) {
    var isSchedule = $("#entry_type_schedule").prop( "checked" );
    var isBudget = $("#entry_type_budget").prop( "checked" );

    if (transactionData.transactionType == "transfer" && isBudget) {
        if (!confirm("Are you sure? This will change transaction type to Withdrawal, and some data might get lost.")) {
            return false;
        }
    }

    var elementTransferLabel = document.getElementById('transaction_type_transfer_label');

    if (isSchedule || isBudget) {
        $("#schedule_container").show();
        $("#transaction_reconciled").prop( "disabled", true ).prop("checked", false);
        $("#transaction_date").prop( "disabled", true ).prop( "value", "");

        if (isBudget) {
            elementTransferLabel.classList.add("disabled");

            //if transaction type was transfer, and budget is selected, switch to withdrawal
            if (transactionData.transactionType == "transfer") {
                $('#transaction_type_withdrawal').click();
            }
        } else {
            elementTransferLabel.classList.remove("disabled");
        }
    } else {
        $("#schedule_container").hide();
        $("#transaction_reconciled").prop( "disabled", false );
        $("#transaction_date").prop( "disabled", false );
        elementTransferLabel.classList.remove("disabled");
    }
  });

  $("#transaction_reconciled").change(function(){
      if (this.checked) {
          $("#entry_type_schedule").prop( "disabled", true ).prop("checked", false);
          $("#entry_type_budget").prop( "disabled", true ).prop("checked", false);
      } else {
          $("#entry_type_schedule").prop( "disabled", false );
          $("#entry_type_budget").prop( "disabled", false );
      }
  });

    //transaction item copy function
	$(".new_transaction_item").on("click",function() {
        create_transaction_item();
        $(".remove_transaction_item").prop('disabled', document.querySelectorAll(".transaction_item_row:not(#transaction_item_prototype)").length <= 1);
    });

    //setup transaction item removal button functionality
	$(".remove_transaction_item").on("click", function() {
		$(this).closest(".transaction_item_row").remove();
        transactionData.updateTotals();

        $(".remove_transaction_item").prop('disabled', document.querySelectorAll(".transaction_item_row:not(#transaction_item_prototype)").length <= 1);

    });

    //setup remaining amount copy function
    $(".load_remainder").on('click', function() {
        try {
            var element = $(this).closest(".transaction_item_row").find("input.transaction_item_amount");
            var remainingAmount = transactionData.remainingAmountNotAllocated || transactionData.remainingAmountToPayeeDefault;

            var amount = math.evaluate(element.val() + "+" + remainingAmount);

            element.val(amount);
            transactionData.updateTotals();

        } catch (err) {

        }
    });

	$(".transaction_item_amount").on('blur', function() {
		/*
			Handle changes to transaction item amount.

			Parse input. Display error, if NaN. Update totals.
        */
        processNumericInput(this);
		transactionData.updateTotals();
	});

	$("#transaction_amount_to").on('blur', function() {
		/*
			Handle changes to transaction total in to field

			Parse input. Display error, if NaN. Update totals and udate slave.
		*/
		var amount = 0;
		try {
			var amount = math.evaluate(this.value.replace(/\s/g,"")) || amount;
			//console.log('result: ' +amount);
			if(amount <= 0) throw Error("Positive number expected");
            $(this).val	(amount);
		} catch (err) {

        }

        $(this).valid();

        transactionData.to.amount = amount;

		transactionData.updateTotals();
		transactionData.updateExchangeRate();
	});

	$("#transaction_amount_from").on('blur', function() {
		/*
			Handle changes to transaction to in from field (which is actually the main field)

			Parse input. Display error, if NaN. Update totals.
		*/
		var amount = 0;
		try {
			var amount = math.evaluate(this.value.replace(/\s/g,"")) || amount;
			//console.log('result: ' +amount);
			if(amount <= 0) throw Error("Positive number expected");
			$(this).val	(amount);
		} catch (err) {

        }

        $(this).valid();

        transactionData.from.amount = amount;

		if (!transactionData.isToCurrencyPresent()) {
			if (transactionData.elements.toAmountInput) {
				transactionData.elements.toAmountInput.val(amount);
			}
		}

		transactionData.updateTotals();
		transactionData.updateExchangeRate();
	});

    //form validation
    /*
	$("#formTransaction").validate({
		debug: true,
		ignore: '.ignore, :hidden',
		rules: {
			date: {
				required: function() {
                        return (   $("#entry_type_schedule").is(':not(:checked)')
                                || $("#entry_type_budget").is(':not(:checked)'));
                    },
				dateISO: true
			},
			account_from: {
                required: true
			},
			account_to: {
                required: true
			},
			amount_from: {
				required: true,
				minStrict: 0,
				number: true
			},
			amount_to: {
				required: true,
				minStrict: 0,
				number: true
			},
			remaining_payee_default: {
				number: true,
				min: 0,
			},
			remaining_not_allocated: {
				number: true,
				min: 0,
            },
            //schedule
            schedule_start: {
				required: function() {
					return (   $("#entry_type_schedule").is(':checked')
					        || $("#entry_type_budget").is(':checked'));
				},
				dateISO: true
            },
            schedule_next: {
				required: function() {
					return (   $("#entry_type_schedule").is(':checked')
					        || $("#entry_type_budget").is(':checked'));
				},
				dateISO: true
            },
            schedule_end: {
				dateISO: true
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
                required: function() {
					return (   $("#entry_type_schedule").is(':checked')
					        || $("#entry_type_budget").is(':checked'));
				}
            }
		},
		messages: {
			remaining_payee_default: {
				min: 'Must be at least 0. Review amounts.',
			},
			remaining_not_allocated: {
				min: 'Must be at least 0. Review amounts.',
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
    */

    //on load initializations

    //hide schedule box if no schedule OR budget is selected
    //TODO: can this be in blade template?
    if (   ! $("#entry_type_schedule").prop( "checked" )
        && ! $("#entry_type_budget").prop( "checked" )) {
        $("#schedule_container").hide();
    } else {
        $("#transaction_reconciled").prop( "disabled", true ).prop("checked", false);
        $("#transaction_date").prop( "disabled", true ).prop( "value", "");

        if ($("#entry_type_budget").prop( "checked" )) {
            $("#transaction_type_transfer_label").addClass( "disabled" );
        }
    }

    //remove delete button from first instance of transaction items
    $(".remove_transaction_item").prop('disabled', document.querySelectorAll(".transaction_item_row:not(#transaction_item_prototype)").length <= 1);

    //add select 2 to all item categories
    document.querySelectorAll(".transaction_item_row:not(#transaction_item_prototype) select.category").forEach(function(s) {
        transactionItemCategorySelectFunctionality($(s));
    });

    //add select 2 to all item tags
    document.querySelectorAll(".transaction_item_row:not(#transaction_item_prototype) select.tag").forEach(function(s) {
        transactionItemTagSelectFunctionality($(s));
    });

    //setup toggle detail functionality
    $(".toggle_transaction_detail").on('click', function(){
        $(this).closest(".transaction_item_row").find(".transaction_detail_container").toggle();
    })

    //adjust amount selectors
    //TODO: is it done be other initialization?
    transactionData.updateTotals();
    transactionData.updateExchangeRate();

    //item list collapse and expand functionality
    $("#itemListCollapse").on('click', function(){
        $(".transaction_item_row").find(".transaction_detail_container").hide();
    });
    $("#itemListShow").on('click', function(){
        $(".transaction_item_row:not(#transaction_item_prototype)").each(function() {
           if(   $(this).find("div.transaction_detail_container input.transaction_item_comment").val() != ""
              || $(this).find("div.transaction_detail_container select").select2('data').length > 0) {
                $(this).find(".transaction_detail_container").show();
            } else {
                $(this).find(".transaction_detail_container").hide();
            }
        });
    });
    $("#itemListExpand").on('click', function(){
        $(".transaction_item_row").find(".transaction_detail_container").show();
    });

    //click the selective show button once
    document.getElementById('itemListShow').click();
});

function transactionItemCategorySelectFunctionality (element) {
    element.select2({
        ajax: {
            url: '/api/assets/category',
            dataType: 'json',
            delay: 150,
            data: function (params) {
                var queryParameters = {
                  q: params.term,
                  active: 1,
                  payee: transactionData.getPayeeData()
                }

                return queryParameters;
            },
            processResults: function (data) {
                return {
                    results: data.map(e => {e.text = e.full_name; return e;})
                };
            },
            cache: true
        },
        selectOnClose: true,
        placeholder: "Select category",
        allowClear: true
    });
}

function transactionItemTagSelectFunctionality (element) {
    element.select2({
        tags: true,
        createTag: function (params) {
            return {
              id: params.term,
              text: params.term,
              newOption: true
            }
        },
        insertTag: function (data, tag) {
            // Insert the tag at the end of the results
            data.push(tag);
        },
        templateResult: function (data) {
            var $result = $("<span></span>");

            $result.text(data.text);

            if (data.newOption) {
              $result.append(" <em>(new)</em>");
            }

            return $result;
        },
        ajax: {
            url:  '/api/assets/tag',
            dataType: 'json',
            delay: 150,
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        //selectOnClose: true,
        placeholder: "Select tag(s)",
        allowClear: true
    });
/*
    if (   typeof itemData !== 'undefined'
        && (itemData.tags || {}).length > 0) {
        var tags = [];
        itemData.tags.forEach(function(item) {
            var text = (!isNaN(parseFloat(item)) ? transactionData.assets.tags[item] : item);
            tags.push(item);
            var newOption = new Option(text, item, false, false);
            newTagSelect.append(newOption)
        });
        newTagSelect.val(tags)
        newTagSelect.trigger('change');
    }
    */
}

function create_transaction_item (itemData) {
    var currentItem = ++window.transactionData.itemRowCounter;

    var template = $( "#transaction_item_prototype" ).clone(true).removeAttr("id")[0];

    // re-define `template`
    template = $(template).attr("id", "transaction_item_row_" + currentItem);

    $("#transaction_item_container").append(template);

    //update input names and other related references
    $("#transaction_item_row_" + currentItem + " select").attr("name", function() { return $(this).attr("name").replace(/#/, currentItem); });
    $("#transaction_item_row_" + currentItem + " input").attr("name", function() { return $(this).attr("name").replace(/#/, currentItem); });

    //update input values
    if (typeof itemData !== 'undefined') {
        $("#transaction_item_row_" + currentItem + " input.transaction_item_amount").val(itemData.amount);
        $("#transaction_item_row_" + currentItem + " input.transaction_item_comment").val(itemData.comment);
    }

    //create select2 functionality
    transactionItemCategorySelectFunctionality($("#transaction_item_row_" + currentItem + " select.category"));

    transactionItemTagSelectFunctionality( $("#transaction_item_row_" + currentItem + " select.tag"));

};

//custom functions for validator
$.validator.addMethod('minStrict', function (value, el, param) {
    if (this.optional(el)) {  // "required" not in force and field is empty
        return true;
    }
    $.validator.messages.minStrict = 'Must be greather than zero';
    return value > param;
});