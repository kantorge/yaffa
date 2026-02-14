<template>
  <div
    class="card mb-3"
    v-if="canFinalize && duplicates.length > 0"
    ref="duplicatesCard"
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
      <span
        class="fa fa-warning text-warning"
        data-bs-toggle="tooltip"
        data-bs-placement="right"
        :title="
          __(
            'The following transactions might be duplicates. Please review before finalizing.',
          )
        "
      ></span>
    </div>
    <div
      class="collapse card-body show"
      aria-expanded="true"
      id="cardDuplicates"
    >
      <div class="list-group">
        <button
          v-for="duplicate in duplicates"
          :key="duplicate.id"
          type="button"
          class="list-group-item list-group-item-action"
          @click="openTransactionModal(duplicate.id)"
        >
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-bold">{{ formatDate(duplicate.date) }}</div>
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
      </div>
    </div>
  </div>
</template>

<script setup>
  import { nextTick, onMounted, ref, watch } from 'vue';
  import { __, initializeBootstrapTooltips } from '../../helpers';

  const props = defineProps({
    aiDocumentId: {
      type: Number,
      required: true,
    },
    canFinalize: {
      type: Boolean,
      required: true,
    },
  });

  const duplicates = ref([]);
  const duplicatesLoading = ref(false);
  const duplicatesCard = ref(null);
  const locale = window.YAFFA?.locale || 'en';

  const initializeTooltips = async () => {
    await nextTick();
    initializeBootstrapTooltips(duplicatesCard.value || document);
  };

  const loadDuplicates = () => {
    if (!props.canFinalize || duplicatesLoading.value) {
      return;
    }

    duplicatesLoading.value = true;

    window.axios
      .post(
        window.route('api.documents.checkDuplicates', {
          aiDocument: props.aiDocumentId,
        }),
      )
      .then((response) => {
        duplicates.value = response.data.duplicates || [];
      })
      .catch((error) => {
        console.error('Failed to load duplicates:', error);
        duplicates.value = [];
      })
      .finally(() => {
        duplicatesLoading.value = false;
        initializeTooltips();
      });
  };

  const formatDate = (value) => {
    if (!value) {
      return __('Not set');
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return date.toLocaleDateString(locale);
  };

  const openTransactionModal = async (transactionId) => {
    if (!transactionId) {
      return;
    }

    try {
      const response = await fetch(`/api/transaction/${transactionId}`);
      const data = await response.json();
      const transaction = data.transaction;

      if (transaction?.date) {
        transaction.date = new Date(transaction.date);
      }
      if (transaction?.transaction_schedule) {
        if (transaction.transaction_schedule.start_date) {
          transaction.transaction_schedule.start_date = new Date(
            transaction.transaction_schedule.start_date,
          );
        }
        if (transaction.transaction_schedule.end_date) {
          transaction.transaction_schedule.end_date = new Date(
            transaction.transaction_schedule.end_date,
          );
        }
        if (transaction.transaction_schedule.next_date) {
          transaction.transaction_schedule.next_date = new Date(
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

  onMounted(() => {
    loadDuplicates();
    initializeTooltips();
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
