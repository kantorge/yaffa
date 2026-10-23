<template>
  <div
    v-if="
      (canFinalize || rechecked) &&
      (duplicatesLoading ||
        duplicates.length > 0 ||
        schedules.length > 0 ||
        documents.length > 0)
    "
    ref="duplicatesCard"
    class="card mb-3"
  >
    <div class="card-header d-flex justify-content-between">
      <div
        class="card-title collapse-control"
        data-coreui-toggle="collapse"
        data-coreui-target="#cardDuplicates"
      >
        <i class="fa fa-angle-down"></i>
        {{ __('Potential duplicates') }}
      </div>
    </div>
    <div
      id="cardDuplicates"
      class="collapse card-body show"
      aria-expanded="true"
    >
      <div v-if="duplicatesLoading" class="text-center text-muted py-2">
        <span
          class="spinner-border spinner-border-sm me-2"
          role="status"
          aria-hidden="true"
        ></span>
        {{ __('Checking for duplicates...') }}
      </div>
      <div v-else class="list-group">
        <button
          v-for="duplicate in duplicates"
          :key="duplicate.id"
          type="button"
          class="list-group-item list-group-item-action"
          @click="openTransactionModal(duplicate.id)"
        >
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-bold">
                {{ formatDate(duplicate.date) }}
              </div>
            </div>
            <div class="text-end">
              <div>{{ duplicate.amount }}</div>
              <div class="badge bg-warning text-dark">
                {{ Math.round(duplicate.similarity * 100) }}%
                {{ __('match') }}
              </div>
            </div>
          </div>
        </button>
        <ScheduleCandidatesPanel
          :candidates="schedules"
          :draft-amount="draftAmount"
          :account-currency="currency"
          @dismiss="dismissSchedule"
        />
        <a
          v-for="document in documents"
          :key="'document-' + document.id"
          :href="document.url"
          class="list-group-item list-group-item-action"
        >
          {{
            __('Open document #:id describing the same purchase', {
              id: document.id,
            })
          }}
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
  import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
  import {
    initializeBootstrapTooltips,
    parseIsoDate,
  } from '@/shared/lib/helpers';
  import { __, toFormattedDate } from '@/shared/lib/i18n';
  import ScheduleCandidatesPanel from '@/import/components/ScheduleCandidatesPanel.vue';

  const props = defineProps({
    aiDocumentId: {
      type: Number,
      required: true,
    },
    canFinalize: {
      type: Boolean,
      required: true,
    },
    currency: {
      type: Object,
      default: null,
    },
    draftAmount: {
      type: [Number, String],
      default: null,
    },
  });

  const duplicates = ref([]);
  const schedules = ref([]);
  const documents = ref([]);
  const duplicatesLoading = ref(false);
  // Set once the check was rerun after a transaction was created from this document
  const rechecked = ref(false);
  const duplicatesCard = ref(null);
  const route = window.route;
  const locale = window.YAFFA?.userSettings?.locale || 'en';

  const initializeTooltips = async () => {
    await nextTick();
    initializeBootstrapTooltips(duplicatesCard.value || document);
  };

  const loadDuplicates = () => {
    if (duplicatesLoading.value) {
      return;
    }

    duplicatesLoading.value = true;

    window.axios
      .post(
        route('api.v1.documents.checkDuplicates', {
          aiDocument: props.aiDocumentId,
        }),
      )
      .then((response) => {
        duplicates.value = response.data.duplicates || [];
        schedules.value = response.data.schedules || [];
        documents.value = response.data.documents || [];
      })
      .catch((error) => {
        console.error('Failed to load duplicates:', error);
        duplicates.value = [];
        schedules.value = [];
        documents.value = [];
      })
      .finally(() => {
        duplicatesLoading.value = false;
        initializeTooltips();
      });
  };

  // The document is finalized by now, so the check is expected to list the new transaction
  // instead of the schedule it was entered from
  const onTransactionCreated = (event) => {
    const transaction = event?.detail?.transaction;
    const documentId =
      transaction?.ai_document_id || transaction?.ai_document?.id;

    if (documentId !== props.aiDocumentId) {
      return;
    }

    rechecked.value = true;
    loadDuplicates();
  };

  const formatDate = (value) => {
    if (!value) {
      return __('Not set');
    }

    return toFormattedDate(value, locale, value, true);
  };

  const openTransactionModal = async (transactionId) => {
    if (!transactionId) {
      return;
    }

    try {
      const response = await fetch(`/api/v1/transactions/${transactionId}`);
      const data = await response.json();
      const transaction = data.transaction;

      if (transaction?.date) {
        transaction.date = parseIsoDate(transaction.date);
      }
      if (transaction?.transaction_schedule) {
        if (transaction.transaction_schedule.start_date) {
          transaction.transaction_schedule.start_date = parseIsoDate(
            transaction.transaction_schedule.start_date,
          );
        }
        if (transaction.transaction_schedule.end_date) {
          transaction.transaction_schedule.end_date = parseIsoDate(
            transaction.transaction_schedule.end_date,
          );
        }
        if (transaction.transaction_schedule.next_date) {
          transaction.transaction_schedule.next_date = parseIsoDate(
            transaction.transaction_schedule.next_date,
          );
        }
      }

      const event = new CustomEvent('showTransactionQuickViewModal', {
        detail: {
          transaction,
          controls: {
            show: true,
            edit: true,
            clone: true,
            skip: true,
            enter: true,
            delete: true,
          },
        },
      });

      window.dispatchEvent(event);
    } catch (error) {
      console.error('Failed to load transaction details:', error);
    }
  };

  const dismissSchedule = (transactionId) => {
    schedules.value = schedules.value.filter(
      (schedule) => schedule.transaction_id !== transactionId,
    );
  };

  onMounted(() => {
    window.addEventListener('transaction-created', onTransactionCreated);
    if (props.canFinalize) {
      loadDuplicates();
    }
    initializeTooltips();
  });

  onBeforeUnmount(() => {
    window.removeEventListener('transaction-created', onTransactionCreated);
  });

  watch(
    () => props.canFinalize,
    (value) => {
      if (value) {
        loadDuplicates();
      }
    },
  );

  watch(duplicates, () => {
    initializeTooltips();
  });
</script>
