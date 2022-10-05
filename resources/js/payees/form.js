import 'select2';

// Common config for preference selects
const config = {
    multiple: true,
    ajax: {
        url: '/api/assets/category',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                q: params.term,
                withInactive: true,
            };
        },
        processResults: function (data) {
            // Filter results that are selected in the other select
            const thisSelect = $(this.$element[0]);
            const otherSelect = $(thisSelect.data('other-select'));
            const otherItems = otherSelect.select2('val');

            return {
                results: data.filter(function(item) {
                    return !otherItems.includes(item.id.toString());
                }),
            };
        },
        cache: true
    },
    selectOnClose: true,
    placeholder: "Select category",
    allowClear: true
};

// Initialize the selects
$('#preferred').select2(config);
$('#not_preferred').select2(config);

// Load default values for the selects
categoryPreferences
.filter(category => category.preferred)
.forEach(category => {
    $('#preferred')
    .append(new Option(category.full_name, category.id, true, true))
    .trigger('change')
    .trigger({
        type: 'select2:select',
        params: {
            data: {
                id: category.id,
                name: category.full_name,
            }
        }
    });
});

categoryPreferences
.filter(category => !category.preferred)
.forEach(category => {
    $('#not_preferred')
    .append(new Option(category.full_name, category.id, true, true))
    .trigger('change')
    .trigger({
        type: 'select2:select',
        params: {
            data: {
                id: category.id,
                name: category.full_name,
            }
        }
    });
});
