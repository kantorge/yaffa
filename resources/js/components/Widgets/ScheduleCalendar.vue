<template>
    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">
                {{ __('Scheduled transaction instances') }}
            </h3>

            <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
            </div>
            <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <Calendar
                class="custom-calendar max-w-full"
                :masks="masks"
                :attributes="attributes"
                :first-day-of-week="2"
                :min-date="minDate"
                :max-date="maxDate"
                disable-page-swipe
                is-expanded
                trim-weeks
                @transition-end="refreshTooltip"
            >
                <template v-slot:day-content="{ day, attributes }">
                    <div>
                    <span class="day-label text-sm">{{ day.day }}</span>
                    <div>
                        <a
                            v-for="item in attributes"
                            :key="item.key"
                            style="margin: 0 1px;"
                            :href="getTransactionLink(item.customData.transaction_config_type, item.customData.id)"
                            v-html="getTransactionTypeIcon(item.customData)"
                        ></a>
                    </div>
                    </div>
                </template>
            </Calendar>
        </div>
    </div>
</template>

<script>
import * as dataTableHelpers from '../dataTableHelper';
import {Calendar} from 'v-calendar';

export default {
    components: {
        dataTableHelpers,
        Calendar,
    },

    methods: {
        getTransactionTypeIcon: function(transaction) {
            return dataTableHelpers.transactionTypeIcon(transaction.transaction_config_type, transaction.transaction_type, this.getTransactionLabel(transaction));
        },
        getTransactionLink: function(type, id) {
            if (type === 'standard') {
                return route('transactions.open.standard', {transaction: id, action: 'enter'});
            }
            if (type === 'investment') {
                return route('transactions.open.investment', {transaction: id, action: 'enter'});
            }
            // Not expected, but fallback to home route
            return route('home');
        },
        getTransactionLabel: function(transaction) {
            if (transaction.transaction_config_type === 'standard') {
                // Capitalize first letter of transaction type
                const type = transaction.transaction_type.charAt(0).toUpperCase() + transaction.transaction_type.slice(1);
                // Return constructed label
                return type + ' ' + transaction.config.amount_to.toLocalCurrency(transaction.currency) + ' from ' + transaction.config.account_from.name + ' to ' + transaction.config.account_to.name;
            }
        },
        refreshTooltip: function() {
            $('[data-toggle="tooltip"]').tooltip();
        }
    },

    data() {
        return {
            transactions: [],
            attributes: [],
            masks: {
                weekdays: 'WWW',
            },
            minDate: null,
            maxDate: null,
        }
    },

    created() {
        let $vm = this;
        axios.get('/api/transactions/get_scheduled_items/schedule')
        .then(function(response) {
            $vm.attributes = response.data.transactions
            // Keep only the transactions with a next date set.
            // Note: the date values are not converted to JavaScript Date objects in general.
            // TODO: is it a valid case for a returned schedule to not have a schedule_config set?
            .filter((transaction) => transaction.schedule_config && transaction.schedule_config.next_date)
            // Map the data to the format required by the calendar component
            .map(function(transaction, index) {
                return {
                    key: index + 1,
                    customData: transaction,
                    dates: new Date(transaction.schedule_config.next_date)
                }
            })

            // Set min and max dates
            $vm.minDate = $vm.attributes.map((transaction) => transaction.dates).reduce(function(a, b) {return (a < b ? a : b);});
            $vm.maxDate = $vm.attributes.map((transaction) => transaction.dates).reduce(function(a, b) {return (a > b ? a : b);});

            // TODO: first load fails to initialize tooltips
            $vm.refreshTooltip();
        })
    },
};
</script>

<style>
    .custom-calendar.vc-container {
        border-radius: 0;
        width: 100%;
    }
    .custom-calendar .vc-header {
        background-color: #f1f5f8;
        padding: 10px 0;
    }
    .custom-calendar .vc-weeks {
        padding: 0;
    }
    .custom-calendar .vc-weekday {
        background-color: #f8fafc;
        border-bottom: 1px solid #eaeaea;
        border-top: 1px solid #eaeaea;
        padding: 5px 0;
    }
    .custom-calendar .vc-day {
        border: 1px solid #b8c2cc;
        padding: 0 5px 3px 5px;
        text-align: left;
        height: 60px;
        min-width: 45px;
        background-color: white;
    }
    .custom-calendar .vc-day-dots {
        margin-bottom: 5px;
    }
</style>
