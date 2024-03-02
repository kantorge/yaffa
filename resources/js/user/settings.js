// Initialize date selectors
import Datepicker from 'vanillajs-datepicker/Datepicker';

const datePickerOptions = {
    weekStart: 1,
    todayBtn: true,
    todayBtnMode: 1,
    todayHighlight: true,
    language: window.YAFFA.language,
    format: 'yyyy-mm-dd',
    autohide: true,
    buttonClass: 'btn',
};

new Datepicker(
    document.getElementById('start_date'),
    datePickerOptions
);

new Datepicker(
    document.getElementById('end_date'),
    datePickerOptions
);

// Finally, initialize tooltips
const tooltipTriggerList = document.querySelectorAll('[data-coreui-toggle="tooltip"]');
[...tooltipTriggerList].map(tooltipTriggerEl => new coreui.Tooltip(tooltipTriggerEl));
