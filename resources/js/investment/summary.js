require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import * as dataTableHelpers from './../components/dataTableHelper';
import {toFormattedCurrency} from '../helpers';

let table = $('#investmentSummary').DataTable({
    data: window.investments,
    columns: [
        {
            data: "name",
            title: __("Name"),
            render: function (data, _type, row) {
                return `<a href="${window.route('investment.show', row.id)}" title="${__('View investment details')}">${data}</a>`;
            },
            type: "html",
        },
        {
            data: "investment_group.name",
            title: __("Group"),
            type: "string"
        },
        {
            data: "active",
            title: __("Active"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "quantity",
            title: __("Quantity"),
            render: function (data, type) {
                if (type === 'display') {
                    return data.toLocaleString(window.YAFFA.locale, {maximumFractionDigits: 2, useGrouping: true});
                }
                return data;
            },
            type: "num",
            className: 'dt-nowrap',
        },
        {
            data: "price",
            title: __("Latest price"),
            render: function (data, type, row) {
                if (type === 'display' && !isNaN(data) && typeof data === "number") {
                    return toFormattedCurrency(data, window.YAFFA.locale, row.currency);
                }

                return data;
            },
            type: "num",
            className: 'dt-nowrap',
        },
        {
            defaultContent: "",
            title: __("Value"),
            render: function (_data, type, row) {
                const value = row.quantity * row.price;

                if (type === 'display') {
                    return toFormattedCurrency(value, window.YAFFA.locale, row.currency);
                }

                return value;
            },
            type: "num",
            className: 'dt-nowrap',
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data) {
                return '<a href="' + route('investment.show', data) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-search" title="' + __('View investment details') + '"></i></a> ' +
                       '<a href="' + route('investment-price.list', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-dollar" title="' + __('View investment price list') + '"></i></a> ';
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [0, 'asc']
    ],
    responsive: true,
    initComplete: function (settings) {
        $(settings.nTable).on("click", "td.activeIcon > i:not(.inProgress)", function () {
            var row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin inProgress');

            // Send request to change investment active state
            $.ajax({
                type: 'PUT',
                url: '/api/assets/investment/' + row.data().id + '/active/' + (row.data().active ? 0 : 1),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    window.investments.filter(investment => investment.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    alert(__('Error changing investment active state'));
                },
                complete: function (_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });
    },
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    scroller: true,
    stateSave: true,
    processing: true,
    paging: false,
});

// Listeners for button filter(s)
dataTableHelpers.initializeFilterButtonsActive(table, 2);
