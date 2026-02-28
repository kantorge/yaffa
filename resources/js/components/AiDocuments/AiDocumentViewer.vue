<template>
  <div class="row">
    <div class="col-12 col-lg-4">
      <div class="card mb-3">
        <div class="card-header">
          <div
            class="card-title collapse-control"
            data-coreui-toggle="collapse"
            data-coreui-target="#cardOverview"
          >
            <i class="fa fa-angle-down"></i>
            {{ __('Overview') }}
          </div>
        </div>
        <div
          class="collapse card-body show"
          aria-expanded="true"
          id="cardOverview"
        >
          <dl class="row mb-0">
            <dt class="col-7">{{ __('Created at') }}</dt>
            <dd class="col-5" :title="aiDocument.created_at">
              {{ createdAtLabel }}
            </dd>
            <dt class="col-7">{{ __('Status') }}</dt>
            <dd class="col-5">
              <span class="badge" :class="statusClass">
                {{ statusLabel }}
              </span>
            </dd>
            <dt class="col-7">{{ __('Source') }}</dt>
            <dd class="col-5">{{ sourceLabel }}</dd>
            <dt class="col-7">{{ __('Processed at') }}</dt>
            <dd
              class="col-5"
              :class="{ 'text-muted': !aiDocument.processed_at }"
            >
              {{ processedAtLabel }}
            </dd>
            <dt class="col-7">{{ __('Files') }}</dt>
            <dd class="col-5">{{ aiDocument.files?.length || 0 }}</dd>
            <dt class="col-7">{{ __('Linked transaction') }}</dt>
            <dd class="col-5">
              <a
                v-if="aiDocument.transaction"
                :href="transactionLink"
                :title="__('View transaction')"
              >
                {{ aiDocument.transaction.id }}
              </a>
              <span v-else class="text-muted">{{ __('Not available') }}</span>
            </dd>
          </dl>
        </div>
      </div>

      <div class="card mb-3" v-if="showExtractedDetails">
        <div
          class="card-header d-flex justify-content-between align-items-center"
        >
          <div
            class="card-title collapse-control"
            data-coreui-toggle="collapse"
            data-coreui-target="#cardExtractedData"
          >
            <i class="fa fa-angle-down"></i>
            {{ __('Extracted data') }}
          </div>
          <button
            class="btn btn-sm btn-outline-primary"
            type="button"
            @click="showExtractedDetailsTab"
            v-if="showExtractedDetails"
          >
            <i class="fa fa-fw fa-info-circle" :title="__('More details')"></i>
          </button>
        </div>
        <div
          class="collapse card-body show"
          aria-expanded="true"
          id="cardExtractedData"
        >
          <dl class="row mb-0">
            <dt class="col-6">{{ __('Transaction type') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted text-italic': !draftTypeLabel }"
            >
              {{ draftTypeLabel || unidentifiedLabel }}
            </dd>
            <dt class="col-6">{{ __('Date') }}</dt>
            <dd
              class="col-6"
              :class="{
                'text-muted text-italic': isUnidentified(draftData.date),
              }"
            >
              {{ formatRawValue(draftData.date) }}
            </dd>

            <template v-if="isInvestment">
              <dt class="col-6">{{ __('Account') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'text-muted text-italic': isUnidentified(rawData.account),
                }"
              >
                <template v-if="matchedEntities.account?.matched">
                  <a
                    v-if="matchedEntities.account?.url"
                    :href="matchedEntities.account.url"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {{ matchedEntities.account.name }}
                  </a>
                  <span v-else>{{ matchedEntities.account.name }}</span>
                  <span class="text-muted small ms-1">
                    <i class="fa fa-check-circle text-success"></i>
                  </span>
                </template>
                <template v-else>
                  {{ formatRawValue(rawData.account) }}
                </template>
              </dd>
              <dt class="col-6">{{ __('Investment') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'text-muted text-italic': isUnidentified(rawData.investment),
                }"
              >
                <template v-if="matchedEntities.investment?.matched">
                  <a
                    v-if="matchedEntities.investment?.url"
                    :href="matchedEntities.investment.url"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {{ matchedEntities.investment.name }}
                  </a>
                  <span v-else>{{ matchedEntities.investment.name }}</span>
                  <span class="text-muted small ms-1">
                    <i class="fa fa-check-circle text-success"></i>
                  </span>
                </template>
                <template v-else>
                  {{ formatRawValue(rawData.investment) }}
                </template>
              </dd>
              <dt class="col-6">{{ __('Quantity') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'text-muted text-italic': isUnidentified(rawData.quantity),
                }"
              >
                {{ formatRawValue(rawData.quantity) }}
              </dd>
              <dt class="col-6">{{ __('Price') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'text-muted text-italic': isUnidentified(rawData.price),
                }"
              >
                {{ formatRawValue(rawData.price) }}
              </dd>
              <dt class="col-6">{{ __('Amount') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'text-muted text-italic': isUnidentified(rawData.amount),
                }"
              >
                {{ formatRawValue(rawData.amount) }}
              </dd>
              <dt class="col-6">{{ __('Currency') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'text-muted text-italic': isUnidentified(rawData.currency),
                }"
              >
                {{ formatRawValue(rawData.currency) }}
              </dd>
            </template>

            <template v-else>
              <template v-if="isTransfer">
                <dt class="col-6">{{ __('Account from') }}</dt>
                <dd
                  class="col-6"
                  :class="{
                    'text-muted text-italic': isUnidentified(
                      rawData.account_from,
                    ),
                  }"
                >
                  <template v-if="matchedEntities.account_from?.matched">
                    <a
                      v-if="matchedEntities.account_from?.url"
                      :href="matchedEntities.account_from.url"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      {{ matchedEntities.account_from.name }}
                    </a>
                    <span v-else>{{ matchedEntities.account_from.name }}</span>
                    <span class="text-muted small ms-1">
                      <i
                        class="fa fa-check-circle text-success"
                        :title="__('Matched database entity')"
                      ></i>
                    </span>
                  </template>
                  <template v-else>
                    {{ formatRawValue(rawData.account_from) }}
                  </template>
                </dd>
                <dt class="col-6">{{ __('Account to') }}</dt>
                <dd
                  class="col-6"
                  :class="{
                    'text-muted text-italic': isUnidentified(
                      rawData.account_to,
                    ),
                  }"
                >
                  <template v-if="matchedEntities.account_to?.matched">
                    <a
                      v-if="matchedEntities.account_to?.url"
                      :href="matchedEntities.account_to.url"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      {{ matchedEntities.account_to.name }}
                    </a>
                    <span v-else>{{ matchedEntities.account_to.name }}</span>
                    <span class="text-muted small ms-1">
                      <i
                        class="fa fa-check-circle text-success"
                        :title="__('Matched database entity')"
                      ></i>
                    </span>
                  </template>
                  <template v-else>
                    {{ formatRawValue(rawData.account_to) }}
                  </template>
                </dd>
              </template>
              <template v-else>
                <dt class="col-6">{{ __('Account') }}</dt>
                <dd
                  class="col-6"
                  :class="{
                    'text-muted text-italic': isUnidentified(rawData.account),
                  }"
                >
                  <template v-if="matchedEntities.account?.matched">
                    <a
                      v-if="matchedEntities.account?.url"
                      :href="matchedEntities.account.url"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      {{ matchedEntities.account.name }}
                    </a>
                    <span v-else>{{ matchedEntities.account.name }}</span>
                    <span class="text-muted small ms-1">
                      <i
                        class="fa fa-check-circle text-success"
                        :title="__('Matched database entity')"
                      ></i>
                    </span>
                  </template>
                  <template v-else>
                    {{ formatRawValue(rawData.account) }}
                  </template>
                </dd>
                <dt class="col-6">{{ __('Payee') }}</dt>
                <dd
                  class="col-6"
                  :class="{
                    'text-muted text-italic': isUnidentified(rawData.payee),
                  }"
                >
                  <template v-if="matchedEntities.payee?.matched">
                    <span>{{ matchedEntities.payee.name }}</span>
                    <span class="text-muted small ms-1">
                      <i
                        class="fa fa-check-circle text-success"
                        :title="__('Matched database entity')"
                      ></i>
                    </span>
                  </template>
                  <template v-else>
                    {{ formatRawValue(rawData.payee) }}
                  </template>
                </dd>
              </template>

              <dt class="col-6">{{ __('Amount') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'text-muted text-italic': isUnidentified(rawData.amount),
                }"
              >
                {{ formatRawValue(rawData.amount) }}
              </dd>
              <dt class="col-6">{{ __('Currency') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'text-muted text-italic': isUnidentified(rawData.currency),
                }"
              >
                {{ formatRawValue(rawData.currency) }}
              </dd>
              <template v-if="showLineItemsCount">
                <dt class="col-6">{{ __('Line items') }}</dt>
                <dd class="col-6">
                  {{ draftData.transaction_items?.length || 0 }}
                </dd>
              </template>
            </template>
          </dl>
        </div>
      </div>

      <AiDocumentDuplicates
        :ai-document-id="aiDocument.id"
        :can-finalize="canFinalize"
      />

      <div class="card mb-3">
        <div class="card-header">
          <div
            class="card-title collapse-control"
            data-coreui-toggle="collapse"
            data-coreui-target="#cardActions"
          >
            <i class="fa fa-angle-down"></i>
            {{ __('Actions') }}
          </div>
        </div>
        <ul
          class="list-group list-group-flush collapse show"
          aria-expanded="true"
          id="cardActions"
        >
          <li
            class="list-group-item d-flex justify-content-between align-items-center"
            v-if="canFinalize"
          >
            {{ __('Finalize transaction') }}
            <button
              class="btn btn-xs btn-primary"
              type="button"
              @click="finalizeDocument"
              :title="__('Finalize transaction')"
            >
              <i class="fa fa-fw fa-edit"></i>
            </button>
          </li>
          <li
            class="list-group-item d-flex justify-content-between align-items-center"
            v-if="canReprocess"
          >
            {{ __('Reprocess document') }}
            <button
              class="btn btn-xs btn-warning"
              type="button"
              @click="reprocessDocument"
              :disabled="isBusy"
              :title="__('Reprocess document')"
            >
              <i class="fa fa-fw fa-repeat" :class="{ 'fa-spin': isBusy }"></i>
            </button>
          </li>
          <li
            class="list-group-item d-flex justify-content-between align-items-center"
          >
            {{ __('Delete document') }}
            <button
              class="btn btn-xs btn-danger"
              type="button"
              @click="deleteDocument"
              :disabled="isBusy"
              :title="__('Delete')"
            >
              <i class="fa fa-fw fa-trash"></i>
            </button>
          </li>
        </ul>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card mb-3">
        <div class="card-header">
          <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
              <button
                class="nav-link active"
                id="nav-document-tab-files"
                data-coreui-toggle="tab"
                data-coreui-target="#document-tab-files"
                type="button"
                role="tab"
                aria-controls="document-tab-files"
                aria-selected="true"
              >
                {{ __('Files') }}
              </button>
            </li>
            <li class="nav-item" v-if="aiDocument.received_mail">
              <button
                class="nav-link"
                id="nav-document-tab-email"
                data-coreui-toggle="tab"
                data-coreui-target="#document-tab-email"
                type="button"
                role="tab"
                aria-controls="document-tab-email"
                aria-selected="false"
              >
                {{ __('Email content') }}
              </button>
            </li>
            <li class="nav-item" v-if="aiDocument.custom_prompt">
              <button
                class="nav-link"
                id="nav-document-tab-prompt"
                data-coreui-toggle="tab"
                data-coreui-target="#document-tab-prompt"
                type="button"
                role="tab"
                aria-controls="document-tab-prompt"
                aria-selected="false"
              >
                {{ __('Custom prompt') }}
              </button>
            </li>
            <li class="nav-item" v-if="showExtractedDetails">
              <button
                class="nav-link"
                id="nav-document-tab-extracted"
                data-coreui-toggle="tab"
                data-coreui-target="#document-tab-extracted"
                type="button"
                role="tab"
                aria-controls="document-tab-extracted"
                aria-selected="false"
              >
                {{ __('Extracted details') }}
              </button>
            </li>
            <li class="nav-item" v-if="hasProcessingHistory">
              <button
                class="nav-link"
                id="nav-document-tab-history"
                data-coreui-toggle="tab"
                data-coreui-target="#document-tab-history"
                type="button"
                role="tab"
                aria-controls="document-tab-history"
                aria-selected="false"
              >
                {{ __('AI chat history') }}
              </button>
            </li>
          </ul>
        </div>
        <div class="card-body">
          <div class="tab-content" id="document-tab-content">
            <div
              class="tab-pane fade show active"
              id="document-tab-files"
              role="tabpanel"
              aria-labelledby="nav-document-tab-files"
              tabindex="0"
            >
              <ai-document-file-viewer
                :files="aiDocument.files || []"
                :ai-document-id="aiDocument.id"
              />
            </div>

            <ai-document-email-viewer
              v-if="aiDocument.received_mail"
              :received-mail="aiDocument.received_mail"
            />

            <div
              v-if="aiDocument.custom_prompt"
              class="tab-pane fade"
              id="document-tab-prompt"
              role="tabpanel"
              aria-labelledby="nav-document-tab-prompt"
              tabindex="0"
            >
              <pre class="mb-0">{{ aiDocument.custom_prompt }}</pre>
            </div>

            <div
              v-if="showExtractedDetails"
              class="tab-pane fade"
              id="document-tab-extracted"
              role="tabpanel"
              aria-labelledby="nav-document-tab-extracted"
              tabindex="0"
            >
              <ai-document-extracted-details
                :draft-data="draftData"
                :draft-type-label="draftTypeLabel"
              />
            </div>

            <div
              v-if="hasProcessingHistory"
              class="tab-pane fade"
              id="document-tab-history"
              role="tabpanel"
              aria-labelledby="nav-document-tab-history"
              tabindex="0"
            >
              <ai-document-processing-history :entries="processingHistory" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <transaction-form-modal-standard :ai-document-id="aiDocument.id" />
    <transaction-form-modal-investment :ai-document-id="aiDocument.id" />
    <transaction-show-modal />
  </div>
</template>

<script setup>
  import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
  import { getTransactionTypeConfig } from '@/helpers';
  import { __ } from '@/i18n';
  import * as toastHelpers from '@/toast';
  import { storeNotification } from '@/handle_notifications';
  import TransactionFormModalStandard from '../TransactionForm/ModalStandard.vue';
  import TransactionFormModalInvestment from '../TransactionForm/ModalInvestment.vue';
  import TransactionShowModal from '../TransactionDisplay/Modal.vue';
  import AiDocumentEmailViewer from './AiDocumentEmailViewer.vue';
  import AiDocumentFileViewer from './AiDocumentFileViewer.vue';
  import AiDocumentExtractedDetails from './AiDocumentExtractedDetails.vue';
  import AiDocumentDuplicates from './AiDocumentDuplicates.vue';
  import AiDocumentProcessingHistory from './AiDocumentProcessingHistory.vue';
  import Swal from 'sweetalert2';

  const aiDocument = ref(window.aiDocument || {});
  const statusLabels = window.aiDocumentStatusLabels || {};
  const sourceLabels = window.aiDocumentSourceLabels || {};
  const isBusy = ref(false);
  const locale = window.YAFFA.userSettings.locale || 'en';
  const unidentifiedLabel = __('Unidentified');

  // Computed properties used in the component
  const createdAtLabel = computed(() => {
    if (!aiDocument.value.created_at) {
      return __('Not set');
    }

    return new Date(aiDocument.value.created_at).toLocaleString(locale);
  });

  const processedAtLabel = computed(() => {
    if (!aiDocument.value.processed_at) {
      return __('Not set');
    }

    return new Date(aiDocument.value.processed_at).toLocaleString(locale);
  });

  const statusLabel = computed(
    () => statusLabels[aiDocument.value.status] || aiDocument.value.status,
  );
  const sourceLabel = computed(
    () =>
      sourceLabels[aiDocument.value.source_type] ||
      aiDocument.value.source_type,
  );

  const statusClass = computed(() => {
    switch (aiDocument.value.status) {
      case 'ready_for_processing':
        return 'bg-info';
      case 'processing':
        return 'bg-primary';
      case 'processing_failed':
        return 'bg-danger';
      case 'ready_for_review':
        return 'bg-warning';
      case 'finalized':
        return 'bg-success';
      default:
        return 'bg-secondary';
    }
  });

  // Computed flag indicating if the document has draft data available for finalization
  const hasDraftData = computed(
    () => !!aiDocument.value.processed_transaction_data,
  );
  // Computed property to access draft transaction data more easily
  const draftData = computed(
    () => aiDocument.value.processed_transaction_data || {},
  );
  const rawData = computed(() => draftData.value?.raw || {});
  const matchedEntities = computed(
    () => draftData.value?.matched_entities || {},
  );
  const draftTransactionType = computed(
    () => rawData.value.transaction_type || draftData.value.transaction_type,
  );

  const isInvestment = computed(
    () => draftData.value.config_type === 'investment',
  );
  const isTransfer = computed(() => draftTransactionType.value === 'transfer');
  const showLineItemsCount = computed(
    () => draftData.value.config_type === 'standard' && !isTransfer.value,
  );

  const draftTypeLabel = computed(() => {
    const rawType = draftData.value?.raw?.transaction_type || '';
    if (!rawType) {
      return __('Not set');
    }

    return getTransactionTypeConfig(rawType).label || rawType;
  });

  const canFinalize = computed(
    () => aiDocument.value.status === 'ready_for_review' && hasDraftData.value,
  );

  const showExtractedDetails = computed(
    () =>
      hasDraftData.value &&
      ['ready_for_review', 'finalized'].includes(aiDocument.value.status),
  );

  const processingHistory = computed(
    () => aiDocument.value.ai_chat_history || [],
  );

  const hasProcessingHistory = computed(
    () =>
      showExtractedDetails.value &&
      Array.isArray(processingHistory.value) &&
      processingHistory.value.length > 0,
  );

  const canReprocess = computed(() =>
    ['ready_for_review', 'processing_failed'].includes(aiDocument.value.status),
  );

  const transactionLink = computed(() =>
    aiDocument.value.transaction
      ? window.route('transaction.open', {
          transaction: aiDocument.value.transaction.id,
          action: 'show',
        })
      : '#',
  );

  const isUnidentified = (value) => {
    return value === null || typeof value === 'undefined' || value === '';
  };

  const formatRawValue = (value) => {
    if (isUnidentified(value)) {
      return unidentifiedLabel;
    }

    return value;
  };

  const buildDraftTransaction = () => {
    // Create a deep copy of the draft data to avoid mutating the original
    const draft = JSON.parse(JSON.stringify(draftData.value || {}));

    draft.config = draft.config || {};
    draft.transaction_items = Array.isArray(draft.transaction_items)
      ? draft.transaction_items
      : [];

    return draft;
  };

  const finalizeDocument = () => {
    // The button should be disabled if there's no draft data, but we add a check here just in case
    if (!hasDraftData.value) {
      toastHelpers.showErrorToast(__('No draft data available to finalize.'));
      return;
    }

    const draft = buildDraftTransaction();

    // Always dispatch event to modal (for both investment and standard transactions)
    window.dispatchEvent(
      new CustomEvent('initiateCreateFromDraft', {
        detail: {
          type: draft.config_type || 'standard',
          transaction: draft,
        },
      }),
    );
  };

  const reprocessDocument = () => {
    if (isBusy.value) {
      return;
    }

    Swal.fire({
      text: __(
        'Reprocessing will remove the previous extraction data and AI chat history. Do you want to continue?',
      ),
      icon: 'warning',
      showCancelButton: true,
      cancelButtonText: __('Cancel'),
      confirmButtonText: __('Reprocess'),
      buttonsStyling: false,
      customClass: {
        confirmButton: 'btn btn-warning',
        cancelButton: 'btn btn-outline-secondary ms-3',
      },
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      isBusy.value = true;

      window.axios
        .post(
          window.route('api.v1.documents.reprocess', {
            aiDocument: aiDocument.value.id,
          }),
        )
        .then((response) => {
          aiDocument.value.status = response.data.status;
          aiDocument.value.processed_transaction_data = null;
          aiDocument.value.ai_chat_history = [];
          toastHelpers.showSuccessToast(response.data.message);
          showFilesTab();
        })
        .catch((error) => {
          toastHelpers.showErrorToast(
            __('Error while reprocessing document: :errorMessage', {
              errorMessage: error.response?.data?.message || error.message,
            }),
          );
        })
        .finally(() => {
          isBusy.value = false;
        });
    });
  };

  const deleteDocument = () => {
    if (isBusy.value) {
      return;
    }

    isBusy.value = true;

    Swal.fire({
      text: __('Are you sure you want to delete this document?'),
      icon: 'warning',
      showCancelButton: true,
      cancelButtonText: __('Cancel'),
      confirmButtonText: __('Delete'),
      buttonsStyling: false,
      customClass: {
        confirmButton: 'btn btn-danger',
        cancelButton: 'btn btn-outline-secondary ms-3',
      },
    }).then((result) => {
      if (!result.isConfirmed) {
        isBusy.value = false;
        return;
      }

      window.axios
        .delete(
          window.route('api.v1.documents.destroy', {
            aiDocument: aiDocument.value.id,
          }),
        )
        .then(() => {
          storeNotification('success', __('Document deleted'), {
            dismissible: true,
          });
          window.location.href = window.route('ai-documents.index');
        })
        .catch((error) => {
          toastHelpers.showErrorToast(
            __('Error while deleting document: :errorMessage', {
              errorMessage: error.response?.data?.message || error.message,
            }),
          );
        })
        .finally(() => {
          isBusy.value = false;
        });
    });
  };

  const showExtractedDetailsTab = () => {
    const tab = new window.coreui.Tab(
      window.document.getElementById('nav-document-tab-extracted'),
    );
    tab.show();
  };

  const showFilesTab = () => {
    const filesTabButton = window.document.getElementById(
      'nav-document-tab-files',
    );

    if (!filesTabButton) {
      return;
    }

    const tab = new window.coreui.Tab(filesTabButton);
    tab.show();
  };

  const refreshDocument = () =>
    window.axios
      .get(
        window.route('api.v1.documents.show', {
          aiDocument: aiDocument.value.id,
        }),
      )
      .then((response) => {
        if (response?.data?.document) {
          aiDocument.value = response.data.document;
        }
      });

  const handleTransactionCreated = (event) => {
    const transaction = event?.detail?.transaction;
    if (!transaction) {
      return;
    }

    const relatedDocumentId =
      transaction.ai_document_id || transaction.ai_document?.id || null;

    if (!relatedDocumentId || relatedDocumentId !== aiDocument.value.id) {
      return;
    }

    refreshDocument();
  };

  onMounted(() => {
    window.addEventListener('transaction-created', handleTransactionCreated);
  });

  onBeforeUnmount(() => {
    window.removeEventListener('transaction-created', handleTransactionCreated);
  });
</script>
