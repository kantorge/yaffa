<template>
  <div>
    <div class="row mb-3">
      <div class="col-12">
        <div class="card">
          <div class="card-body">
            <div class="row">
              <div class="col-md-4 form-group">
                <label for="account">{{ __('Target account') }}</label>
                <select id="account" class="form-control"></select>
              </div>
              <div class="col-md-4 form-group">
                <label for="qif_file">{{ __('QIF File') }}</label
                ><br />
                <input
                  type="file"
                  class="form-control-file"
                  id="qif_file"
                  @change="onFileChange"
                  :disabled="!accountSelected"
                  accept=".qif"
                />
              </div>
              <div class="col-md-2 form-group">
                <label for="date_format">{{ __('Date format') }}</label>
                <input
                  type="text"
                  class="form-control"
                  id="date_format"
                  v-model="dateFormat"
                  placeholder="YYYY-MM-DD"
                />
              </div>
              <div class="col-md-2 form-group">
                <label for="reset">&nbsp;</label>
                <button
                  type="button"
                  class="btn btn-primary"
                  @click="resetForm"
                >
                  {{ __('Reset form') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-sm-12">
        <div class="card">
          <div class="card-header">
            <div class="card-title">{{ __('Identified transactions') }}</div>
          </div>
          <div class="card-body">
            <DataTable
              :data="transactions"
              :columns="columns"
              :options="tableOptions"
              class="table table-bordered table-hover"
              width="100%"
            />
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <div class="card-title collapse-control">
              <span
                class="collapsed"
                data-coreui-toggle="collapse"
                href="#collapse-qif-unmatched-container"
                aria-expanded="true"
                aria-controls="collapse-qif-unmatched-container"
              >
                <i class="fa fa-angle-down"></i>
                {{ __('Unmatched rows') }}
              </span>
            </div>
          </div>
          <div
            class="card-body collapse table-responsive no-padding"
            id="collapse-qif-unmatched-container"
          >
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <th v-for="header in unmatchedHeaders" :key="header">
                    {{ header }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in unmatchedRows" :key="idx">
                  <td v-for="header in unmatchedHeaders" :key="header">
                    {{ row[header] }}
                  </td>
                </tr>
                <tr v-if="unmatchedRows.length === 0">
                  <td :colspan="unmatchedHeaders.length">
                    {{ __('No unmatched rows') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import DataTable from 'datatables.net-vue3';
  import DataTablesCore from 'datatables.net';
  import DataTablesBootstrap5 from 'datatables.net-bs5';
  import Swal from 'sweetalert2';

  import { parseQIF } from './qifParser';
  import { __ } from '../helpers';

  DataTable.use(DataTablesCore);
  DataTable.use(DataTablesBootstrap5);
  import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';

  require('select2');

  function mapQifTransactionToApp(tx, account) {
    // Map QIF transaction to app's internal structure
    // Try to infer type: deposit, withdrawal, transfer
    let type = 'deposit';
    let amount = Number(tx.amount);
    if (isNaN(amount)) amount = 0;
    if (amount < 0) type = 'withdrawal';
    if (tx.category && tx.category.toLowerCase().includes('transfer'))
      type = 'transfer';

    // Map to app structure
    const mapped = {
      date: tx.date ? new Date(tx.date) : null,
      payee: tx.payee || '',
      amount: amount,
      memo: tx.memo || '',
      category: tx.category || '',
      subcategory: tx.subcategory || '',
      number: tx.number || '',
      clearedStatus: tx.clearedStatus || '',
      transaction_config_type: 'standard',
      transaction_type: {
        name: type,
        amount_multiplier: type === 'withdrawal' ? -1 : 1,
      },
      config: {
        amount_from: type === 'withdrawal' ? Math.abs(amount) : undefined,
        amount_to: type !== 'withdrawal' ? Math.abs(amount) : undefined,
        account_from: type === 'withdrawal' ? account : undefined,
        account_to: type !== 'withdrawal' ? account : undefined,
      },
      handled: false,
      comment: tx.memo || '',
      draftId: Math.random().toString(36).substr(2, 9),
      similarTransactions: false,
      relatedSchedules: false,
      quickRecordingPossible: false,
    };
    return mapped;
  }

  export default {
    name: 'QifImport',
    components: {
      DataTable,
    },
    data() {
      return {
        accountSelected: false,
        selectedAccount: null,
        dateFormat: 'YYYY.MM.DD',
        transactions: [],
        unmatchedRows: [],
        unmatchedHeaders: [],
        columns: [
          {
            data: 'date',
            title: this.__('Date'),
            render: (data) =>
              data
                ? new Date(data).toLocaleDateString(window.YAFFA?.locale)
                : '',
          },
          { data: 'payee', title: this.__('Payee') },
          {
            data: 'amount',
            title: this.__('Amount'),
            render: (data) =>
              data !== undefined
                ? Number(data).toLocaleString(window.YAFFA?.locale)
                : '',
          },
          { data: 'memo', title: this.__('Memo') },
          { data: 'category', title: this.__('Category') },
          { data: 'subcategory', title: this.__('Subcategory') },
          { data: 'number', title: this.__('Number') },
          { data: 'clearedStatus', title: this.__('Cleared') },
          {
            data: 'actions',
            title: this.__('Actions'),
            orderable: false,
            searchable: false,
            render: () => '',
          },
        ],
        tableOptions: {
          createdRow: (row, data) => {
            // Add custom row actions if needed
          },
        },
      };
    },
    mounted() {
      this.initSelect2();
    },
    methods: {
      __,
      initSelect2() {
        const vm = this;
        $('#account')
          .select2({
            multiple: false,
            ajax: {
              url: '/api/assets/account',
              dataType: 'json',
              delay: 150,
              data: (params) => ({ q: params.term }),
              processResults: (data) => ({
                results: data.map((account) => ({
                  id: account.id,
                  text: account.name,
                })),
              }),
              cache: true,
            },
            selectOnClose: false,
            placeholder: this.__('Select account'),
            allowClear: true,
            theme: 'bootstrap-5',
          })
          .on('select2:select', function (e) {
            vm.selectedAccount = e.params.data;
            vm.accountSelected = true;
          })
          .on('select2:unselect', function () {
            vm.selectedAccount = null;
            vm.accountSelected = false;
          });
      },
      async onFileChange(event) {
        const file = event.target.files[0];
        if (!file) return;
        const text = await file.text();
        try {
          const blocks = parseQIF(text, { dateFormat: this.dateFormat });
          let allTransactions = [];
          let unmatched = [];
          const account = this.selectedAccount
            ? { id: this.selectedAccount.id, name: this.selectedAccount.text }
            : null;
          blocks.forEach((block) => {
            if (block.transactions && block.transactions.length) {
              block.transactions.forEach((tx) => {
                try {
                  allTransactions.push(mapQifTransactionToApp(tx, account));
                } catch (e) {
                  unmatched.push(tx);
                }
              });
            }
          });
          this.transactions = allTransactions;
          this.unmatchedRows = unmatched;
          this.unmatchedHeaders = unmatched.length
            ? Object.keys(unmatched[0])
            : [];
        } catch (e) {
          Swal.fire({
            icon: 'error',
            text: this.__('Failed to parse QIF file.'),
          });
        }
      },
      resetForm() {
        Swal.fire({
          title: this.__('Are you sure you want to reset the form?'),
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: this.__('Yes'),
          cancelButtonText: this.__('Cancel'),
        }).then((result) => {
          if (!result.isConfirmed) return;
          this.selectedAccount = null;
          this.accountSelected = false;
          this.transactions = [];
          this.unmatchedRows = [];
          this.unmatchedHeaders = [];
          window.$('#account').val(null).trigger('change');
          document.getElementById('qif_file').value = '';
          document.getElementById('qif_file').disabled = true;
        });
      },
    },
  };
</script>
