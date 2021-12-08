import Datepicker from 'vanillajs-datepicker/Datepicker';

$(function () {
    new Datepicker(
        document.getElementById('date'),
        {
            weekStart: 1,
            todayBtn: true,
            todayBtnMode: 1,
            todayHighlight: true,
            format: 'yyyy-mm-dd',
            autohide: true,
            buttonClass: 'btn',
        }
    );
});
