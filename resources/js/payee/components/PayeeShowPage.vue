<template>
  <div>
    <div class="row">
      <div class="col-12 col-lg-3">
        <payee-overview-card
          :payee="payee"
          :overview="overview"
          :base-currency="baseCurrency"
        ></payee-overview-card>

        <div id="payeeActionsCard" class="card mb-3">
          <div class="card-header">
            <div
              class="card-title collapse-control"
              data-coreui-toggle="collapse"
              data-coreui-target="#payeeActions"
            >
              <i class="fa fa-angle-down"></i>
              {{ __('Actions') }}
            </div>
          </div>
          <ul
            id="payeeActions"
            class="list-group list-group-flush collapse show"
          >
            <li
              class="list-group-item d-flex justify-content-between align-items-center"
            >
              {{ __('Edit payee') }}
              <button
                id="payeeEditButton"
                type="button"
                class="btn btn-sm btn-primary"
                :title="__('Edit payee')"
                @click="$refs.payeeForm.show(payee.id)"
              >
                <i class="fa fa-edit"></i>
              </button>
            </li>
            <li
              class="list-group-item d-flex justify-content-between align-items-center"
            >
              {{ __('New transaction') }}
              <a
                class="btn btn-sm btn-success"
                :href="newTransactionUrl"
                :title="__('New transaction')"
              >
                <i class="fa fa-cart-plus"></i>
              </a>
            </li>
            <li
              class="list-group-item d-flex justify-content-between align-items-center"
            >
              {{ __('Find transactions') }}
              <a
                class="btn btn-sm btn-primary"
                :href="findTransactionsUrl"
                :title="__('Find transactions')"
              >
                <i class="fa fa-search"></i>
              </a>
            </li>
            <li
              class="list-group-item d-flex justify-content-between align-items-center"
            >
              {{ __('Merge into an other payee') }}
              <a
                class="btn btn-sm btn-primary"
                :href="mergeUrl"
                :title="__('Merge into an other payee')"
              >
                <i class="fa fa-random"></i>
              </a>
            </li>
            <li
              v-if="overview.count === 0"
              class="list-group-item d-flex justify-content-between align-items-center"
            >
              {{ __('Delete payee') }}
              <button
                id="payeeDeleteButton"
                type="button"
                class="btn btn-sm btn-danger"
                :title="__('Delete payee')"
                @click="deletePayee"
              >
                <i class="fa fa-trash"></i>
              </button>
            </li>
          </ul>
        </div>

        <date-range-filter-card
          ref="dateRange"
          :initial-preset="defaultDatePreset"
          @update="onUpdateDateRange"
        ></date-range-filter-card>

        <payee-category-recommendation
          v-if="suggestion"
          :suggestion="suggestion"
          @accepted="onSuggestionAccepted"
        ></payee-category-recommendation>

        <payee-profile-card :profile="profile"></payee-profile-card>

        <payee-schedules-card :payee-id="payee.id"></payee-schedules-card>
        <similar-payees-card
          :payee-id="payee.id"
          :payee-name="payee.name"
        ></similar-payees-card>
      </div>

      <div class="col-12 col-lg-9">
        <transaction-report-tabs
          :transactions="transactions"
          :busy="busy"
          :use-breakdown-cache="false"
          @drill-down="onDrillDown"
          @transaction-deleted="onTransactionDeleted"
        ></transaction-report-tabs>
      </div>
    </div>

    <payee-form
      id="editPayeeModal"
      ref="payeeForm"
      action="edit"
      @payee-selected="onPayeeUpdated"
    ></payee-form>
    <transaction-show-modal></transaction-show-modal>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { processTransaction } from '@/shared/lib/helpers';
  import { confirmDelete } from '@/shared/lib/confirm';
  import * as toastHelpers from '@/shared/lib/toast';
  import DateRangeFilterCard from '@/shared/ui/date/DateRangeFilterCard.vue';
  import TransactionReportTabs from '@/shared/ui/reports/TransactionReportTabs.vue';
  import TransactionShowModal from '@/transactions/components/display/Modal.vue';
  import PayeeCategoryRecommendation from '@/dashboard/components/widgets/PayeeCategoryRecommendation.vue';
  import PayeeForm from './PayeeForm.vue';
  import PayeeOverviewCard from './PayeeOverviewCard.vue';
  import PayeeProfileCard from './PayeeProfileCard.vue';
  import PayeeSchedulesCard from './PayeeSchedulesCard.vue';
  import SimilarPayeesCard from './SimilarPayeesCard.vue';

  export default {
    name: 'PayeeShowPage',
    components: {
      DateRangeFilterCard,
      PayeeCategoryRecommendation,
      PayeeForm,
      PayeeOverviewCard,
      PayeeProfileCard,
      PayeeSchedulesCard,
      SimilarPayeesCard,
      TransactionReportTabs,
      TransactionShowModal,
    },
    data() {
      return {
        payee: window.payee,
        overview: window.overview,
        baseCurrency: window.baseCurrency,
        suggestion: window.categorySuggestion || null,
        profile: window.payeeProfile || null,
        dateFrom: null,
        dateTo: null,
        busy: false,
        transactions: [],
        requestCounter: 0,
      };
    },
    computed: {
      // "none" means: don't load data until the user picks a range
      defaultDatePreset() {
        return window.YAFFA.userSettings.account_details_date_range || 'none';
      },
      findTransactionsUrl() {
        return this.route('reports.transactions', { payees: [this.payee.id] });
      },
      mergeUrl() {
        return this.route('payees.merge.form', { payeeSource: this.payee.id });
      },
      newTransactionUrl() {
        return this.route('transaction.create', {
          type: 'standard',
          account_to: this.payee.id,
          transaction_type: 'withdrawal',
        });
      },
    },
    methods: {
      onUpdateDateRange(event) {
        this.dateFrom = event.dateFrom;
        this.dateTo = event.dateTo;
        this.getTransactions();
      },
      getTransactions() {
        const requestId = ++this.requestCounter;
        this.busy = true;

        window.axios
          .get('/api/v1/transactions', {
            params: {
              payees: [this.payee.id],
              date_from: this.dateFrom,
              date_to: this.dateTo,
            },
          })
          .then((response) => {
            // A newer range selection superseded this request
            if (requestId === this.requestCounter) {
              this.transactions = response.data.data.map(processTransaction);
            }
          })
          .catch((error) => {
            toastHelpers.showErrorToast(
              __('Error getting transactions: :error', { error }),
            );
          })
          .finally(() => {
            if (requestId === this.requestCounter) {
              this.busy = false;
            }
          });
      },
      onTransactionDeleted(transactionId) {
        this.transactions = this.transactions.filter(
          (transaction) => Number(transaction.id) !== Number(transactionId),
        );
        this.refreshOverview();
      },
      // The lifetime figures come from the server, so a deletion needs a fresh copy
      refreshOverview() {
        window.axios
          .get(
            this.route('api.v1.payees.overview', {
              accountEntity: this.payee.id,
            }),
          )
          .then((response) => {
            this.overview = response.data;
          })
          .catch(() => {
            toastHelpers.showErrorToast(
              __('Error while refreshing the overview'),
            );
          });
      },
      // No in-page drill-down here: hand over to Find transactions
      onDrillDown(event) {
        window.location.href = this.route('reports.transactions', {
          payees: [this.payee.id],
          categories: event.categories,
          date_from: event.dateFrom,
          date_to: event.dateTo,
        });
      },
      onPayeeUpdated() {
        // Name, default category and preferences are shown in several places
        window.location.reload();
      },
      onSuggestionAccepted(suggestion) {
        this.payee.config.category = {
          id: suggestion.max_category_id,
          full_name: suggestion.category,
        };
      },
      deletePayee() {
        confirmDelete(__('Are you sure to want to delete this item?')).then(
          (result) => {
            if (!result.isConfirmed) {
              return;
            }

            window.axios
              .delete(
                this.route('api.v1.account-entities.destroy', this.payee.id),
              )
              .then(() => {
                window.location.href = this.route('account-entity.index', {
                  type: 'payee',
                });
              })
              .catch((error) => {
                // E.g. "Payee is in use": the overview doesn't count schedules
                toastHelpers.showErrorToast(
                  error.response?.data?.error ||
                    __('Error while trying to delete payee'),
                );
              });
          },
        );
      },
      __,
    },
  };
</script>
