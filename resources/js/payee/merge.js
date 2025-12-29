import { loadSelect2Language } from '../helpers';
import select2 from 'select2';
select2();
loadSelect2Language(window.YAFFA.language);

// Add select2 functionality to payee_source select
const selectorSourcePayee = '#payee_source';
const selectorTargetPayee = '#payee_target';
$(selectorSourcePayee).select2({
    theme: "bootstrap-5",
    placeholder: __('Select payee to be merged'),
    allowClear: true,
    selectOnClose: false,
    ajax: {
        url: '/api/assets/payee',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                _token: csrfToken,
                q: params.term, // search term
                account_type: 'payee',
                withInactive: true,
            };
        },
        processResults: function (data) {
            // Exclude payee in target select
            let targetPayee = $(selectorTargetPayee).select2('data');
            if (targetPayee.length > 0) {
                data = data.filter(function (item) {
                    return item.id != targetPayee[0].id;
                });
            }

            return {
                results: data,
            };
        },
        cache: true,
    },
});

// Load default value for source payee if provided in query parameter
let payeeSource = window.payeeSource || null;
if (payeeSource) {
    $(selectorSourcePayee)
        .append(new Option(payeeSource.name, payeeSource.id, true, true))
        .trigger('change');
}

// Add select2 functionality to payee_target select
$(selectorTargetPayee).select2({
    theme: "bootstrap-5",
    placeholder: __('Select payee to be merged into'),
    allowClear: true,
    selectOnClose: false,
    ajax: {
        url: '/api/assets/payee',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                _token: csrfToken,
                q: params.term, // search term
                account_type: 'payee',
                withInactive: true,
            };
        },
        processResults: function (data) {
            // Exclude payee in source select
            let sourcePayee = $(selectorSourcePayee).select2('data');
            if (sourcePayee.length > 0) {
                data = data.filter(function (item) {
                    return item.id != sourcePayee[0].id;
                });
            }

            return {
                results: data,
            };
        },
        cache: true,
    },
});

// Add confirm dialog to submit button
$('#merge-payees-form').on('submit', function (e) {
    // Validate if both select2 inputs are not empty
    let source = $(selectorSourcePayee).select2('data');
    let target = $(selectorTargetPayee).select2('data');

    if (source.length === 0 || target.length === 0) {
        e.preventDefault();
        alert(__('Please select payees to be merged'));
        return;
    } else {
        // Validate if both select2 inputs are not the same
        if (source[0].id === target[0].id) {
            e.preventDefault();
            alert(__('Please select different payees to be merged'));
            return;
        }
    }

    // Validate if action radio button is selected
    let action = $('input[name=action]:checked').val();
    if (typeof action === "undefined") {
        e.preventDefault();
        alert(__('Please select an action'));
        return;
    }

    if (!confirm(__('Are you sure you want to merge these payees?'))) {
        e.preventDefault();
    }
});

// Cancel button behaviour
$('#cancel').on('click', function () {
    if (confirm(__('Are you sure you want to discard any changes?'))) {
        window.history.back();
    }
});
