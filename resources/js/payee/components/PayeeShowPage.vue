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
            <div class="card-title">{{ __('Actions') }}</div>
          </div>
          <div class="card-body d-flex flex-wrap gap-2">
            <button
              id="payeeEditButton"
              type="button"
              class="btn btn-sm btn-primary"
              @click="$refs.payeeForm.show(payee.id)"
            >
              <i class="fa fa-edit me-1"></i>{{ __('Edit') }}
            </button>
            <a class="btn btn-sm btn-success" :href="newTransactionUrl">
              <i class="fa fa-cart-plus me-1"></i>{{ __('New transaction') }}
            </a>
            <a
              class="btn btn-sm btn-outline-primary"
              :href="findTransactionsUrl"
            >
              <i class="fa fa-search me-1"></i>{{ __('Find transactions') }}
            </a>
            <a class="btn btn-sm btn-outline-primary" :href="mergeUrl">
              <i class="fa fa-random me-1"></i>{{ __('Merge') }}
            </a>
            <button
              v-if="overview.count === 0"
              id="payeeDeleteButton"
              type="button"
              class="btn btn-sm btn-danger"
              @click="deletePayee"
            >
              <i class="fa fa-trash me-1"></i>{{ __('Delete') }}
            </button>
          </div>
        </div>

        <div v-if="suggestion" id="payeeSuggestionCard" class="card mb-3">
          <div class="card-body">
            <p class="mb-2">
              💡
              {{
                __('Suggested default category: :category', {
                  category: suggestion.category,
                })
              }}
            </p>
            <button
              type="button"
              class="btn btn-sm btn-success me-2"
              :disabled="suggestionBusy"
              @click="acceptSuggestion"
            >
              {{ __('Accept suggestion') }}
            </button>
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary"
              :disabled="suggestionBusy"
              @click="dismissSuggestion"
            >
              {{ __('Dismiss') }}
            </button>
          </div>
        </div>

        <date-range-filter-card
          ref="dateRange"
          initial-preset="previous365Days"
          @update="onUpdateDateRange"
        ></date-range-filter-card>

        <div class="mb-3">
          <button
            v-if="!isAllTime"
            id="loadAllTransactionsButton"
            type="button"
            class="btn btn-outline-primary w-100"
            :disabled="busy"
            @click="$refs.dateRange.clearDates()"
          >
            {{ __('Load all transactions') }}
          </button>
          <div v-else class="alert alert-info mb-0">
            {{ __('Showing all transactions. Large histories can be slow.') }}
            <button
              type="button"
              class="btn btn-sm btn-link p-0 align-baseline"
              @click="showLastYear"
            >
              {{ __('Back to the last 12 months') }}
            </button>
          </div>
        </div>

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
  import PayeeForm from './PayeeForm.vue';
  import PayeeOverviewCard from './PayeeOverviewCard.vue';
  import PayeeSchedulesCard from './PayeeSchedulesCard.vue';
  import SimilarPayeesCard from './SimilarPayeesCard.vue';

  export default {
    name: 'PayeeShowPage',
    components: {
      DateRangeFilterCard,
      PayeeForm,
      PayeeOverviewCard,
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
        suggestionBusy: false,
        dateFrom: null,
        dateTo: null,
        busy: false,
        transactions: [],
        requestCounter: 0,
      };
    },
    computed: {
      isAllTime() {
        return !this.dateFrom && !this.dateTo;
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
      showLastYear() {
        const card = this.$refs.dateRange;
        card.selectedPreset = 'previous365Days';
        card.onPresetChange();
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
      acceptSuggestion() {
        this.suggestionBusy = true;
        window.axios
          .post(
            this.route('api.v1.payees.category-suggestions.accept', {
              accountEntity: this.payee.id,
              category: this.suggestion.max_category_id,
            }),
          )
          .then(() => {
            this.payee.config.category = {
              id: this.suggestion.max_category_id,
              full_name: this.suggestion.category,
            };
            this.suggestion = null;
            toastHelpers.showSuccessToast(__('Default category updated'));
          })
          .catch(() => {
            toastHelpers.showErrorToast(
              __('Error while updating default category'),
            );
          })
          .finally(() => {
            this.suggestionBusy = false;
          });
      },
      dismissSuggestion() {
        this.suggestionBusy = true;
        window.axios
          .post(
            this.route('api.v1.payees.category-suggestions.dismiss', {
              accountEntity: this.payee.id,
            }),
          )
          .then(() => {
            this.suggestion = null;
          })
          .catch(() => {
            toastHelpers.showErrorToast(
              __('Error while dismissing suggestion'),
            );
          })
          .finally(() => {
            this.suggestionBusy = false;
          });
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
              .catch(() => {
                toastHelpers.showErrorToast(
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
