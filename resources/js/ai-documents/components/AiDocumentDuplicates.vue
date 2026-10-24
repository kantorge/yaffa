<template>
  <div
    v-if="
      (canFinalize || rechecked) &&
      (duplicatesLoading ||
        duplicates.length > 0 ||
        schedules.length > 0 ||
        relatedDocuments.length > 0 ||
        documents.length > 0)
    "
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
      <div v-else class="d-flex flex-column gap-3">
        <DuplicateCandidatesPanel
          :candidates="duplicateCandidates"
          :draft-amount="draftAmount"
          :account-currency="currency"
          @dismiss="dismissDuplicate"
        />
        <ScheduleCandidatesPanel
          :candidates="schedules"
          :draft-amount="draftAmount"
          :account-currency="currency"
          @dismiss="dismissSchedule"
        />
        <RelatedAiDocumentsPanel
          :candidates="relatedDocuments"
          :account-currency="currency"
          @dismiss="dismissRelatedDocument"
        />
        <div v-if="documents.length" class="list-group">
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
  </div>
</template>

<script setup>
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
  import { __ } from '@/shared/lib/i18n';
  import DuplicateCandidatesPanel from '@/import/components/DuplicateCandidatesPanel.vue';
  import RelatedAiDocumentsPanel from '@/import/components/RelatedAiDocumentsPanel.vue';
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
  const relatedDocuments = ref([]);
  const documents = ref([]);
  const duplicatesLoading = ref(false);
  // Set once the check was rerun after a transaction was created from this document
  const rechecked = ref(false);
  const route = window.route;

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
        relatedDocuments.value = response.data.related_documents || [];
        documents.value = response.data.documents || [];
      })
      .catch((error) => {
        console.error('Failed to load duplicates:', error);
        duplicates.value = [];
        schedules.value = [];
        relatedDocuments.value = [];
        documents.value = [];
      })
      .finally(() => {
        duplicatesLoading.value = false;
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

  // The legacy duplicate shape, in the one the import's panel displays
  const duplicateCandidates = computed(() =>
    duplicates.value.map((duplicate) => ({
      transaction_id: duplicate.id,
      confidence_score: duplicate.similarity,
      matched_on: duplicate.matched_on || [],
      summary: {
        date: duplicate.date,
        comment: duplicate.comment,
        amount: Number(duplicate.amount),
      },
    })),
  );

  const dismissDuplicate = (transactionId) => {
    duplicates.value = duplicates.value.filter(
      (duplicate) => duplicate.id !== transactionId,
    );
  };

  const dismissRelatedDocument = (documentId) => {
    relatedDocuments.value = relatedDocuments.value.filter(
      (document) => document.ai_document_id !== documentId,
    );
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
</script>
