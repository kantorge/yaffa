
import 'select2';
$.fn.select2.amd.define(
    'select2/i18n/' + window.YAFFA.language,
    [],
    require("select2/src/js/select2/i18n/" + window.YAFFA.language)
);

import { __ } from '../helpers';

// Add select2 functionality to payee_source select
const selectorSourceCategory = '#category_source';
const selectorTargetCategory = '#category_target';

$(selectorSourceCategory).select2({
    placeholder: () => __('Select category to be merged'),
    theme: 'bootstrap-5',
    allowClear: true,
    selectOnClose: false,
    ajax: {
        url: '/api/assets/category',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                _token: csrfToken,
                q: params.term,
                withInactive: true,
            };
        },
        processResults: function (data) {
            // Exclude category in target select
            let targetCategory = $(selectorTargetCategory).select2('data');
            if (targetCategory.length > 0) {
                const targetCategoryId = Number(targetCategory[0].id);
                data = data.filter(function (item) {
                    return item.id !== targetCategoryId;
                });
            }

            return {
                results: data,
            };
        },
        cache: true,
    },
})
.on('select2:select', function (e) {
    // When a category is selected, get all its details and mark if it is a parent category
    $.ajax({
        url:  '/api/assets/category/' + e.params.data.id,
        data: {
            _token: csrfToken,
        }
    })
    .done(data => {
        $(selectorSourceCategory).data('parent', !data.parent);
    });
})
.on('select2:unselect', function () {
    $(selectorSourceCategory).data('parent', null);
});

// Load default value for source category if provided in query parameter
let categorySource = window.categorySource || null;
if (categorySource.id) {
    $(selectorSourceCategory)
        .append(new Option(categorySource.full_name, categorySource.id, true, true))
        .trigger({
            type: 'select2:select',
            params: {
                data: categorySource
            }
        })
        .trigger('change');
}

// Add select2 functionality to category_target select
$(selectorTargetCategory).select2({
    placeholder: () => __('Select category to be merged into'),
    theme: 'bootstrap-5',
    allowClear: true,
    selectOnClose: false,
    ajax: {
        url: '/api/assets/category',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                _token: csrfToken,
                q: params.term, // search term
                withInactive: true,
            };
        },
        processResults: function (data) {
            //Exclude caegory in source select
            let sourceCategory = $(selectorSourceCategory).select2('data');
            if (sourceCategory.length > 0) {
                const sourceCategoryId = Number(sourceCategory[0].id);
                data = data.filter(function (item) {
                    return item.id !== sourceCategoryId;
                });
            }

            return {
                results: data,
            };
        },
        cache: true,
    },
})
.on('select2:select', function (e) {
    // When a category is selected, get all its details and mark if it is a parent category
    $.ajax({
        url:  '/api/assets/category/' + e.params.data.id,
        data: {
            _token: csrfToken,
        }
    })
    .done(data => {
        $(selectorTargetCategory).data('parent', !data.parent);
    });
})
.on('select2:unselect', function () {
    $(selectorTargetCategory).data('parent', null);
});

// Add confirm dialog to submit button
$('#merge-categories-form').on('submit', function (e) {
    // Validate if both select2 inputs are not empty
    let source = $(selectorSourceCategory).select2('data');
    let target = $(selectorTargetCategory).select2('data');

    if (source.length === 0 || target.length === 0) {
        e.preventDefault();
        alert(__('Please select categories to be merged'));
        return;
    }

    // Validate if both select2 inputs are not the same
    if (source[0].id === target[0].id) {
        e.preventDefault();
        alert(__('Please select different categories to be merged'));
        return;
    }

    // Validate if action radio button is selected
    let action = $('input[name=action]:checked').val();
    if (typeof action === 'undefined') {
        e.preventDefault();
        alert(__('Please select an action'));
        return;
    }

    // Validate invalid combination where source category is a parent, and target category is a child
    if ($(selectorSourceCategory).data('parent') === true && $(selectorTargetCategory).data('parent') === false) {
        e.preventDefault();
        alert(__('You cannot merge a parent category into a child category.'));
        return;
    }

    if (!confirm(__('Are you sure you want to merge these categories?'))) {
        e.preventDefault();
    }
});

// Cancel button behaviour
document.getElementById('cancel').addEventListener('click', function () {
    if (confirm(__('Are you sure you want to discard any changes?'))) {
        window.history.back();
    }
});
