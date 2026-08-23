<template>
  <div class="modal fade" id="modal-budget-quickview">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{ __('Details of budget #:budget', { budget: budget.id }) }}
          </h5>
          <button
            type="button"
            class="btn-close"
            data-coreui-dismiss="modal"
            :aria-label="__('Close')"
          ></button>
        </div>
        <div class="modal-body" v-if="budget.id">
          <dl class="row mb-0">
            <dt class="col-4">{{ __('Category') }}</dt>
            <dd class="col-8">{{ budget.category?.full_name || budget.category?.name }}</dd>

            <dt class="col-4">{{ __('Account') }}</dt>
            <dd class="col-8" :class="budget.account ? '' : 'text-muted text-italic'">
              {{ budget.account?.name || __('No account (base currency)') }}
            </dd>

            <dt class="col-4">{{ __('Type') }}</dt>
            <dd class="col-8">{{ __(capitalize(budget.transaction_type)) }}</dd>

            <dt class="col-4">{{ __('Amount') }}</dt>
            <dd class="col-8">{{ formattedAmount }}</dd>

            <dt class="col-4">{{ __('Cadence') }}</dt>
            <dd class="col-8">{{ cadenceText }}</dd>

            <dt class="col-4">{{ __('Inflation') }}</dt>
            <dd class="col-8" :class="budget.inflation ? '' : 'text-muted text-italic'">
              {{ budget.inflation ? __(':rate% per year', { rate: budget.inflation }) : __('Not set') }}
            </dd>

            <dt class="col-4">{{ __('Active') }}</dt>
            <dd class="col-8">
              <i
                :class="budget.active ? 'fa fa-check text-success' : 'fa fa-remove text-danger'"
              ></i>
            </dd>

            <template v-if="budget.comment">
              <dt class="col-4">{{ __('Comment') }}</dt>
              <dd class="col-8">{{ budget.comment }}</dd>
            </template>
          </dl>
        </div>
        <div class="modal-footer" v-if="budget.id">
          <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">
            {{ __('Cancel') }}
          </button>
          <button type="button" class="btn btn-primary" @click="editFromQuickView">
            <i class="fa fa-fw fa-edit"></i> {{ __('Edit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency } from '@/shared/lib/i18n';
  import { scheduleCadenceText } from '@/shared/lib/helpers';

  export default {
    name: 'BudgetQuickView',
    props: {
      locale: {
        type: String,
        default: () => window.YAFFA.userSettings.locale,
      },
    },
    emits: ['edit'],
    data() {
      return {
        budget: {},
        modal: undefined,
      };
    },
    computed: {
      formattedAmount() {
        if (!this.budget.id) {
          return '';
        }

        const currency =
          this.budget.account?.config?.currency
          || window.YAFFA.userSettings.baseCurrency;

        const prefix = this.budget.transaction_type === 'withdrawal' ? '- ' : '+ ';

        return prefix + toFormattedCurrency(this.budget.amount, this.locale, currency, 'detailed');
      },
      cadenceText() {
        return scheduleCadenceText(this.budget);
      },
    },
    methods: {
      show(budget) {
        this.budget = budget;
        this.modal.show();
      },
      editFromQuickView() {
        this.modal.hide();
        this.$emit('edit', this.budget.id);
      },
      capitalize(string) {
        return string ? string[0].toUpperCase() + string.slice(1) : '';
      },
      __,
    },
    mounted() {
      this.modal = new coreui.Modal(document.getElementById('modal-budget-quickview'));
    },
  };
</script>
