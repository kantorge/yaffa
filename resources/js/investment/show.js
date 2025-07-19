window.calculateYears = function (from, to) {
    const diffMs = to - from;
    const diffDate = new Date(diffMs); // milliseconds from epoch
    return Math.abs(diffDate.getUTCFullYear() - 1970);
}

// Datatables filtering
/*
$.fn.dataTable.ext.search.push(
    function (settings, data) {
        const min = datepickerFrom.getDate();
        const max = datepickerTo.getDate();
        const date = new Date(data[0]);

        return (date.getTime() >= min.getTime() && date.getTime() <= max.getTime());
    }
);
*/

// Initialize the Vue component for the content display
import { createApp } from 'vue';
const app = createApp({});

// Add global translator function
app.config.globalProperties.__ = window.__;

import InvestmentDisplayContainer from './../components/InvestmentDisplay/InvestmentDisplayContainer.vue';
app.component('InvestmentDisplayContainer', InvestmentDisplayContainer);

app.mount('#app')
