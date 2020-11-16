window.datePickerStandardSettings = {
    singleDatePicker: true,
    showDropdowns: true,
    locale: {
        format: 'YYYY-MM-DD'
    },
    autoApply: true
};

window.clickCancel = function() {
    if(confirm('Are you sure you want to discard any changes?')) {
        window.history.back();
    }
    return false;
}

window.processNumericInput = function (element) {
    var amount = 1;
    try {
        var amount = math.evaluate(element.value.replace(/\s/g,""));
        //console.log('result: ' +amount);
        if(amount <= 0) throw Error("Positive number expected");
        $(element).closest(".form-group").removeClass("has-error");
        $(element).val	(amount);
    } catch (err) {
        $(element).closest(".form-group").addClass("has-error");
    }

    $(element).valid();
}
