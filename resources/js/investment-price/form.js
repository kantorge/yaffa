require( 'daterangepicker');


$(function () {
    $('#date').daterangepicker({
        singleDatePicker: true,
        showDropdowns: true,
        locale: {
            format: 'YYYY-MM-DD'
        },
        autoApply: true
    });
});
