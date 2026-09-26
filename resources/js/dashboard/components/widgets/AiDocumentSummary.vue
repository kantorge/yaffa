<template>
  <div v-if="state !== 'hidden'" id="widgetAiDocumentSummary" class="card mb-4">
    <div class="card-header">
      <div class="card-title">
        {{ __('widget.aiDocumentSummary.cardTitle') }}
      </div>
    </div>
    <ul v-if="state === 'loading'" class="list-group list-group-flush">
      <li
        v-for="i in 4"
        :key="i"
        aria-hidden="true"
        class="list-group-item placeholder-glow"
      >
        <span class="placeholder col-12"></span>
      </li>
    </ul>
    <ul v-if="state === 'error'" class="list-group list-group-flush">
      <li class="list-group-item list-group-item-danger">
        {{ __('widget.aiDocumentSummary.loadError') }}
      </li>
    </ul>
    <ul v-if="state === 'data-available'" class="list-group list-group-flush">
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a :href="indexUrl()" class="text-decoration-none text-body">
          {{ __('widget.aiDocumentSummary.total') }}
        </a>
        <span class="badge bg-secondary rounded-pill">{{ summary.total }}</span>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a
          :href="indexUrl('ready_for_review')"
          class="text-decoration-none text-body"
        >
          {{ __('widget.aiDocumentSummary.readyForReview') }}
        </a>
        <span
          class="badge rounded-pill"
          :class="summary.ready_for_review > 0 ? 'bg-warning' : 'bg-secondary'"
        >
          {{ summary.ready_for_review }}
        </span>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a
          :href="indexUrl('processing_failed')"
          class="text-decoration-none text-body"
        >
          {{ __('widget.aiDocumentSummary.processingFailed') }}
        </a>
        <span
          class="badge rounded-pill"
          :class="summary.processing_failed > 0 ? 'bg-danger' : 'bg-secondary'"
        >
          {{ summary.processing_failed }}
        </span>
      </li>
      <li
        v-if="summary.oldest_created_at"
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a
          :href="indexUrl('ready_for_review', oldestDateString)"
          class="text-decoration-none text-body"
          :title="__('widget.aiDocumentSummary.oldestDocumentTitle')"
        >
          {{ __('widget.aiDocumentSummary.oldestDocument') }}
        </a>
        <span class="small">
          {{ oldestDateLabel }}
        </span>
      </li>
    </ul>
    <div v-if="state === 'data-available'" class="card-footer text-end">
      <a :href="indexUrl()" class="btn btn-sm btn-outline-primary">
        {{ __('widget.aiDocumentSummary.viewDocuments') }}
      </a>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedDate } from '@/shared/lib/i18n';

  export default {
    name: 'AiDocumentSummary',

    data() {
      return {
        // Expected values: loading, data-available, error, hidden
        state: 'hidden',
        summary: {
          total: 0,
          ready_for_review: 0,
          processing_failed: 0,
          oldest_created_at: null,
        },
        locale: window.YAFFA.userSettings.locale || 'en',
      };
    },

    computed: {
      oldestDateString() {
        if (!this.summary.oldest_created_at) {
          return null;
        }
        return new Date(this.summary.oldest_created_at)
          .toISOString()
          .slice(0, 10);
      },

      oldestDateLabel() {
        if (!this.summary.oldest_created_at) {
          return null;
        }
        return toFormattedDate(
          new Date(this.summary.oldest_created_at),
          this.locale,
          null,
        );
      },
    },

    created() {
      this.state = 'loading';

      axios
        .get(window.route('api.v1.documents.summary'))
        .then((response) => {
          if (!response.data.active_provider) {
            this.state = 'hidden';
            return;
          }

          this.summary = response.data;
          this.state = 'data-available';
        })
        .catch(() => {
          if (this.summary.total !== 0 || this.state !== 'hidden') {
            this.state = 'error';
          }
        });
    },

    methods: {
      __,

      indexUrl(status = null, dateFrom = null) {
        const params = new URLSearchParams();

        if (status) {
          params.set('status', status);
        }

        if (dateFrom) {
          params.set('date_from', dateFrom);
        }

        const query = params.toString();
        const base = window.route('ai-documents.index');

        return query ? `${base}?${query}` : base;
      },
    },
  };
</script>
