<template>
  <div v-if="state !== 'hidden'" id="widgetRechargeSummary" class="card mb-4">
    <div class="card-header">
      <div class="card-title">
        {{ __('Recharges') }}
      </div>
    </div>
    <ul v-if="state === 'loading'" class="list-group list-group-flush">
      <li
        v-for="i in 2"
        :key="i"
        aria-hidden="true"
        class="list-group-item placeholder-glow"
      >
        <span class="placeholder col-12"></span>
      </li>
    </ul>
    <ul v-if="state === 'error'" class="list-group list-group-flush">
      <li class="list-group-item list-group-item-danger">
        {{ __('Error while loading recharge data') }}
      </li>
    </ul>
    <ul v-if="state === 'data-available'" class="list-group list-group-flush">
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a
          :href="route('reports.recharges.untagged')"
          class="text-decoration-none text-body"
        >
          {{ __('Items missing a recharge tag') }}
        </a>
        <span
          class="badge rounded-pill"
          :class="summary.untagged_items > 0 ? 'bg-warning' : 'bg-secondary'"
          data-testid="recharge-untagged-count"
        >
          {{ summary.untagged_items }}
        </span>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a
          :href="route('reports.recharges.open')"
          class="text-decoration-none text-body"
        >
          {{ __('Open recharge tags') }}
        </a>
        <span
          class="badge rounded-pill"
          :class="summary.open_tags > 0 ? 'bg-info' : 'bg-secondary'"
          data-testid="recharge-open-count"
        >
          {{ summary.open_tags }}
        </span>
      </li>
      <li
        v-if="summary.settled_active_tags > 0"
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a
          :href="route('reports.recharges.open')"
          class="text-decoration-none text-body"
        >
          {{ __('Settled tags to deactivate') }}
        </a>
        <span class="badge rounded-pill bg-success">
          {{ summary.settled_active_tags }}
        </span>
      </li>
    </ul>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'RechargeSummary',

    data() {
      return {
        // Expected values: loading, data-available, error, hidden
        state: 'loading',
        summary: {
          untagged_items: 0,
          open_tags: 0,
          settled_active_tags: 0,
        },
      };
    },

    created() {
      axios
        .get(window.route('api.v1.reports.recharges.summary'))
        .then((response) => {
          // Hidden until a category requires a tag
          if (!response.data.enabled) {
            this.state = 'hidden';
            return;
          }

          this.summary = response.data;
          this.state = 'data-available';
        })
        .catch(() => {
          this.state = 'error';
        });
    },

    methods: {
      __,
    },
  };
</script>
