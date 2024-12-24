/*
// Import external libraries
require ('datatables.net-bs5');
require('datatables.net-responsive-bs5');

// Import dataTable helper functions
import * as dataTableHelpers from './../components/dataTableHelper'

// Define selector constants
const tableSelector = '#dataTable';


// Function to reload table data
function reloadTable() {
    document.getElementById('reload').setAttribute('disabled','disabled');
    table.ajax.reload(function() {
        document.getElementById('reload').removeAttribute('disabled');

        // (Re-)Initialize tooltips in table
        $('[data-toggle="tooltip"]').tooltip();

        // Update values in the charts


    });
}


*/

import { createApp } from 'vue'
const app = createApp({})
import FindTransactions from "../components/FindTransactions.vue";
app.component('find-transactions', FindTransactions)
app.mount('#app')
