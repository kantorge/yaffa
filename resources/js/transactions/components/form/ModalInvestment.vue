<template>
  <div class="modal fade" id="modal-transaction-form-investment" tabindex="-1">
    <div class="modal-dialog modal-xxl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{ modalTitle }}
          </h5>
          <button
            type="button"
            class="btn-close"
            data-coreui-dismiss="modal"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <transaction-form-investment
            ref="form"
            :action="action"
            :transaction="transactionData"
            :simplified="true"
            :fromModal="true"
            :ai-document-id="aiDocumentId"
            :dropdown-parent-selector="'#modal-transaction-form-investment'"
            @cancel="onCancel"
            @success="onSuccess"
          ></transaction-form-investment>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import TransactionFormInvestment from './TransactionFormInvestment.vue';
  import * as helpers from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import { confirmAction } from '@/shared/lib/confirm';

  export default {
    name: 'CreateInvestmentTransactionModal',
    components: {
      TransactionFormInvestment,
    },
    props: {
      transaction: {
        type: Object,
        default: {
          transaction_type: 'buy',
          date: new Date(),
          schedule: false,
          reconciled: false,
          comment: null,
          config: {
            account_id: null,
            investment_id: null,
            price: null,
            quantity: null,
            dividend: null,
            commission: null,
            tax: null,
          },
        },
      },
      aiDocumentId: {
        type: Number,
        default: null,
      },
    },
    data() {
      let data = {
        action: 'create',
        // Set right before a programmatic hide() so the hide.coreui.modal listener
        // lets it through once without re-running the dirty check.
        forceClose: false,
      };
      data.transactionData = Object.assign({}, this.transaction);
      return data;
    },
    methods: {
      hide() {
        this.forceClose = true;
        this.modal.hide();
      },
      onCancel() {
        this.hide();
      },
      // Cancelable pre-dismiss hook (backdrop click, Esc, close button) - ask for
      // confirmation only if the form has unsaved changes. The in-form Cancel button
      // already runs its own dirty check before emitting 'cancel', which routes
      // through hide() and is let through here via forceClose.
      onHide(event) {
        if (this.forceClose) {
          this.forceClose = false;
          return;
        }

        if (!this.$refs.form?.isDirty()) {
          return;
        }

        event.preventDefault();

        confirmAction(__('Are you sure you want to discard any changes?'), {
          icon: 'warning',
          confirmButtonText: __('Discard changes'),
          target: '#modal-transaction-form-investment',
        }).then((result) => {
          if (result.isConfirmed) {
            this.hide();
          }
        });
      },
      onSuccess(transaction, options) {
        toastHelpers.showToast(
          __('Success'),
          __('Transaction added.'),
          'bg-success',
          {
            headerSmall: helpers.transactionLink(
              transaction.id,
              __('Go to transaction'),
            ),
          },
        );

        // Check if investment price was stored
        if (options.investmentPriceStoredResult) {
          if (options.investmentPriceStoredResult === 'success') {
            toastHelpers.showSuccessToast(this.__('Investment price stored'));
          } else if (options.investmentPriceStoredResult === 'skipped') {
            toastHelpers.showWarningToast(
              this.__('Price for this date already exists and was not updated'),
            );
          } else if (options.investmentPriceStoredResult === 'error') {
            toastHelpers.showErrorToast(
              this.__('Failed to store investment price'),
            );
          }
        }

        // Emit a custom event about the new transaction to be displayed
        let transactionEvent = new CustomEvent('transaction-created', {
          detail: {
            // Pass the entire transaction object to the event
            transaction: transaction,
          },
        });
        window.dispatchEvent(transactionEvent);

        // Hide the modal
        this.hide();
      },
      onInitiateEnterInstance(transaction) {
        this.action = 'enter';
        this.transactionData = transaction;

        this.modal.show();
      },
      onInitiateCreateDraft(transaction) {
        this.action = 'finalize';
        this.transactionData = transaction;

        this.modal.show();
      },
      handleInitiateEnterInstance(event) {
        // Validate that transaction type is investment
        if (event.detail.transaction.config_type !== 'investment') {
          return;
        }

        this.onInitiateEnterInstance(event.detail.transaction);
      },
      handleInitiateCreateFromDraft(event) {
        // Validate that transaction type is investment
        if (event.detail.type !== 'investment') {
          return;
        }

        this.onInitiateCreateDraft(event.detail.transaction);
      },
    },
    mounted() {
      // Set up event listener for global scope about new schedule instance to be opened in modal editor
      window.addEventListener(
        'initiateEnterInstance',
        this.handleInitiateEnterInstance,
      );

      // Set up event listener for global scope about new transaction draft to be opened in modal editor
      window.addEventListener(
        'initiateCreateFromDraft',
        this.handleInitiateCreateFromDraft,
      );

      // Initialize modal
      this.modal = new coreui.Modal(
        document.getElementById('modal-transaction-form-investment'),
      );
      document
        .getElementById('modal-transaction-form-investment')
        .addEventListener('hide.coreui.modal', this.onHide);
    },
    beforeUnmount() {
      // Clean up event listeners when component is destroyed
      window.removeEventListener(
        'initiateEnterInstance',
        this.handleInitiateEnterInstance,
      );
      window.removeEventListener(
        'initiateCreateFromDraft',
        this.handleInitiateCreateFromDraft,
      );
      document
        .getElementById('modal-transaction-form-investment')
        .removeEventListener('hide.coreui.modal', this.onHide);
    },
    computed: {
      modalTitle() {
        const titles = new Map([
          ['create', __('Add new transaction')],
          ['edit', __('Modify existing transaction')],
          ['clone', __('Clone existing transaction')],
          ['enter', __('Enter scheduled transaction instance')],
          ['replace', __('Clone scheduled transaction and close base item')],
          ['finalize', __('Finalize transaction draft')],
        ]);

        return titles.get(this.action);
      },
    },
  };
</script>

<style scoped>
  @media (min-width: 1200px) {
    .modal-xxl {
      --cui-modal-width: 1800px;
    }
  }
</style>
