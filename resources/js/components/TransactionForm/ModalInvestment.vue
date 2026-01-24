<template>
  <div class="modal fade" id="modal-transaction-form-investment">
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
            :action="action"
            :transaction="transactionData"
            :simplified="true"
            :fromModal="true"
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
  import * as helpers from '@/helpers';
  import * as toastHelpers from '@/toast';

  export default {
    name: 'CreateInvestmentTransactionModal',
    components: {
      TransactionFormInvestment,
    },
    props: {
      transaction: {
        type: Object,
        default: {
          transaction_type: {
            name: 'Buy',
          },
          date: new Date(),
          schedule: false,
          budget: false,
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
    },
    data() {
      let data = {
        action: 'create',
      };
      data.transactionData = Object.assign({}, this.transaction);
      return data;
    },
    methods: {
      onCancel() {
        this.modal.hide();
      },
      onSuccess(transaction) {
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

        // Emit a custom event about the new transaction to be displayed
        let transactionEvent = new CustomEvent('transaction-created', {
          detail: {
            // Pass the entire transaction object to the event
            transaction: transaction,
          },
        });
        window.dispatchEvent(transactionEvent);

        // Hide the modal
        this.modal.hide();
      },
      onInitiateEnterInstance(transaction) {
        this.action = 'enter';
        this.transactionData = transaction;

        this.modal.show();
      },
      onInitiateCreateDraft(transaction) {
        this.action = 'create';
        this.transactionData = transaction;

        this.modal.show();
      },
    },
    mounted() {
      let $vm = this;

      // Set up event listener for global scope about new schedule instance to be opened in modal editor
      window.addEventListener('initiateEnterInstance', function (event) {
        // Validate that transaction type is investment
        if (event.detail.transaction.config_type !== 'investment') {
          return;
        }

        $vm.onInitiateEnterInstance(event.detail.transaction);
      });

      // Set up event listener for global scope about new transaction draft to be opened in modal editor
      window.addEventListener('initiateCreateFromDraft', function (event) {
        // Validate that transaction type is investment
        if (event.detail.type !== 'investment') {
          return;
        }

        $vm.onInitiateCreateDraft(event.detail.transaction);
      });

      // Initialize modal
      this.modal = new coreui.Modal(
        document.getElementById('modal-transaction-form-investment'),
      );
    },
    computed: {
      modalTitle() {
        const titles = new Map([
          ['create', __('Add new transaction')],
          ['edit', __('Modify existing transaction')],
          ['clone', __('Clone existing transaction')],
          ['enter', __('Enter scheduled transaction instance')],
          ['replace', __('Clone scheduled transaction and close base item')],
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
