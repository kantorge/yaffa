
require('select2');

// Read csrf token from meta tag
const csrfToken = $('meta[name="csrf-token"]').attr('content');

// Add select2 functionality to payee_source select
$('#category_source').select2({
    placeholder: 'Select category to be merged',
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
            //Exclude category in target select
            let targetCategory = $('#category_target').select2('data');
            if (targetCategory.length > 0) {
                data = data.filter(function (item) {
                    return item.id != targetCategory[0].id;
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
    $.ajax({
        url:  '/api/assets/category/' + e.params.data.id,
        data: {
            _token: csrfToken,
        }
    })
    .done(data => {
        $('#category_source').data('parent', !data.parent);
    });
})
.on('select2:unselect', function () {
    $('#category_source').data('parent', null);
});

// Load default value for source category if provided in query parameter
if (categorySource) {
    $('#category_source')
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
$('#category_target').select2({
    placeholder: 'Select category to be merged into',
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
            let sourceCategory = $('#category_source').select2('data');
            if (sourceCategory.length > 0) {
                data = data.filter(function (item) {
                    return item.id != sourceCategory[0].id;
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
    $.ajax({
        url:  '/api/assets/category/' + e.params.data.id,
        data: {
            _token: csrfToken,
        }
    })
    .done(data => {
        $('#category_target').data('parent', !data.parent);
    });
})
.on('select2:unselect', function () {
    $('#category_target').data('parent', null);
});

// Add confirm dialog to submit button
$('#merge-categories-form').on('submit', function (e) {
    // Validate if both select2 inputs are not empty
    let source = $('#category_source').select2('data');
    let target = $('#category_target').select2('data');

    if (source.length == 0 || target.length == 0) {
        e.preventDefault();
        alert('Please select categories to be merged');
        return;
    } else {
        // Validate if both select2 inputs are not the same
        if (source[0].id == target[0].id) {
            e.preventDefault();
            alert('Please select different categories to be merged');
            return;
        }
    }

    // Validate if action radio button is selected
    let action = $('input[name=action]:checked').val();
    if (action == undefined) {
        e.preventDefault();
        alert('Please select an action');
        return;
    }

    // Validate invalid combination where source category is a parent, and target category is a child
    if ($('#category_source').data('parent') === true && $('#category_target').data('parent') === false) {
        e.preventDefault();
        alert('Cannot merge a parent category into a child category.');
        return;
    }

    if (!confirm('Are you sure you want to merge these categories?')) {
        e.preventDefault();
    }
});

// Cancel button behaviour
$('#cancel').on('click', function (e) {
    if(confirm('Are you sure you want to discard any changes?')) {
        window.history.back();
    }
});
