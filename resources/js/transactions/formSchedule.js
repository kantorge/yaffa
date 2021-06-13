require('daterangepicker');

$( function () {
    $('#schedule_start').daterangepicker(datePickerStandardSettings);
    $('#schedule_next').daterangepicker(datePickerStandardSettings);
    $('#schedule_end').daterangepicker(datePickerStandardSettings);

    //interval
    $("#schedule_interval").on('blur', function(){
        processNumericInput(this);
    });

    //count
    $("#schedule_count").on('blur', function(){
        processNumericInput(this);
    });

});
