<template>
  <div id="widgetTransactionTemplates" class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="card-title mb-0">
        {{ __('widget.transactionTemplates.cardTitle') }}
      </div>
      <a
        :href="indexUrl"
        class="btn btn-sm btn-outline-secondary"
        :title="__('widget.transactionTemplates.manage')"
      >
        <i class="fa fa-fw fa-gear"></i>
      </a>
    </div>
    <ul v-if="state === 'loading'" class="list-group list-group-flush">
      <li
        v-for="i in 3"
        :key="i"
        aria-hidden="true"
        class="list-group-item placeholder-glow"
      >
        <span class="placeholder col-12"></span>
      </li>
    </ul>
    <ul v-else-if="state === 'error'" class="list-group list-group-flush">
      <li class="list-group-item list-group-item-danger">
        {{ __('widget.transactionTemplates.loadError') }}
      </li>
    </ul>
    <div v-else-if="templates.length === 0" class="card-body">
      <p class="mb-2 text-muted">
        {{ __('widget.transactionTemplates.empty') }}
      </p>
      <a :href="indexUrl" class="btn btn-sm btn-outline-primary">
        {{ __('widget.transactionTemplates.createFirst') }}
      </a>
    </div>
    <ul v-else class="list-group list-group-flush">
      <li
        v-for="template in templates"
        :key="template.id"
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <div class="text-truncate me-2">
          <div class="fw-semibold text-truncate">{{ template.name }}</div>
          <small v-if="template.payee_name" class="text-muted">
            {{ template.payee_name }}
          </small>
        </div>
        <button
          class="btn btn-sm btn-primary flex-shrink-0"
          type="button"
          :data-testid="'use-template-' + template.id"
          :disabled="busyId === template.id"
          @click="use(template)"
        >
          {{ __('widget.transactionTemplates.use') }}
        </button>
      </li>
    </ul>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { useTemplate } from '@/transaction-templates/useTemplate';

  const LIMIT = 6;

  export default {
    name: 'TransactionTemplates',

    data() {
      return {
        // Expected values: loading, loaded, error
        state: 'loading',
        templates: [],
        busyId: null,
        indexUrl: window.route('transaction-templates.index'),
      };
    },

    created() {
      axios
        .get(
          window.route('api.v1.transaction-templates.index', {
            featured: 1,
            limit: LIMIT,
          }),
        )
        .then((response) => {
          this.templates = response.data;
          this.state = 'loaded';
        })
        .catch(() => {
          this.state = 'error';
        });
    },

    methods: {
      __,

      use(template) {
        this.busyId = template.id;
        useTemplate(template.id).finally(() => {
          this.busyId = null;
        });
      },
    },
  };
</script>
