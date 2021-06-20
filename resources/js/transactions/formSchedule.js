$( function () {
    //interval
    $("#schedule_interval").on('blur', function(){
        processNumericInput(this);
    });

    //count
    $("#schedule_count").on('blur', function(){
        processNumericInput(this);
    });

});
