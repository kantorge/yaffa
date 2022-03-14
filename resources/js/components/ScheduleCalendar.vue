<template>
    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">Scheduled transaction instances</h3>

            <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
            </div>
            <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <v-calendar
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
                        :href="getTransactionLink(item.customData.transaction_type, item.customData.id)"
                        v-html="getTransactionTypeIcon(item.customData)"
                        ></a>
                    </div>
                    </div>
                </template>
            </v-calendar>
        </div>
    </div>
</template>

<script>
import * as dataTableHelpers from './../components/dataTableHelper';

export default {
    components: {
        dataTableHelpers
    },

    methods: {
        getTransactionTypeIcon: function(transaction) {
            return dataTableHelpers.transactionTypeIcon(transaction.transaction_type, transaction.transaction_name, this.getTransactionLabel(transaction));
        },
        getTransactionLink: function(type, id) {
            if (type === 'Standard') {
                return route('transactions.openStandard', {transaction: id, action: 'enter'});
            }
            if (type === 'Investment') {
                return route('transactions.openInvestment', {transaction: id, action: 'enter'});
            }
            return route('home');
        },
        getTransactionLabel: function(transaction) {
            if (transaction.transaction_type === 'Standard') {
                if (transaction.transaction_name === 'withdrawal') {
                    return 'Withdraw ' + transaction.amount.toLocalCurrency(transaction.currency) + ' from ' + transaction.account_from_name + ' to ' + transaction.account_to_name;
                }

                if (transaction.transaction_name === 'deposit') {
                    return 'Deposit ' + transaction.amount.toLocalCurrency(transaction.currency) + ' from ' + transaction.account_from_name + ' to ' + transaction.account_to_name;
                }

                if (transaction.transaction_name === 'transfer') {
                    return 'Transfer ' + transaction.amount.toLocalCurrency(transaction.currency) + ' from ' + transaction.account_from_name + ' to ' + transaction.account_to_name;
                }
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
        axios.get('/api/transactions/get_scheduled_items')
        .then(function(response) {
            $vm.transactions = response.data.transactions;
            $vm.attributes= $vm.transactions.filter((transaction) => transaction.schedule_config && transaction.schedule_config.next_date)
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
    .vc-header {
      background-color: #f1f5f8;
      padding: 10px 0;
    }
    .vc-weeks {
      padding: 0;
    }
    .vc-weekday {
      background-color: #f8fafc;
      border-bottom: 1px solid #eaeaea;
      border-top: 1px solid #eaeaea;
      padding: 5px 0;
    }
    .vc-day {
      border: 1px solid #b8c2cc;
      padding: 0 5px 3px 5px;
      text-align: left;
      height: 60px;
      min-width: 45px;
      background-color: white;
    }
    .vc-day-dots {
      margin-bottom: 5px;
    }
</style>
