window.processNumericInput = function (element) {

    var amount = 0;
    try {
        var amount = math.evaluate(element.value.replace(/\s/g,""));

        if(amount <= 0) throw Error("Positive number expected");
        $(element).closest(".form-group").removeClass("has-error");
        $(element).val (amount);
    } catch (err) {
        $(element).closest(".form-group").addClass("has-error");
    }

    $(element).valid();
}
