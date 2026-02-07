<template>
  <div class="row">
    <div class="col-12 col-lg-3">
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

      <div class="card mb-3" v-if="hasDraftData">
        <div class="card-header">
          <div
            class="card-title collapse-control"
            data-coreui-toggle="collapse"
            data-coreui-target="#cardExtractedData"
          >
            <i class="fa fa-angle-down"></i>
            {{ __('Extracted data') }}
          </div>
        </div>
        <div
          class="collapse card-body show"
          aria-expanded="true"
          id="cardExtractedData"
        >
          <dl class="row mb-0">
            <dd class="col-6">{{ __('Transaction type') }}</dd>
            <dt class="col-6">{{ draftTypeLabel }}</dt>
            <dd class="col-6">{{ __('Date') }}</dd>
            <dt class="col-6">{{ draftData.date || __('Not set') }}</dt>
            <dd class="col-6">{{ __('Account') }}</dd>
            <dt class="col-6">{{ draftData.raw?.account || __('Not set') }}</dt>
            <dd class="col-6">{{ __('Payee') }}</dd>
            <dt class="col-6">{{ draftData.raw?.payee || __('Not set') }}</dt>
            <dd class="col-6">{{ __('Amount') }}</dd>
            <dt class="col-6">{{ draftData.raw?.amount || __('Not set') }}</dt>
          </dl>
        </div>
      </div>

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

    <div class="col-12 col-lg-9">
      <div class="card mb-3" v-if="duplicateWarnings.length">
        <div class="card-body">
          <div class="alert alert-warning mb-0">
            <strong>{{ __('Possible duplicates found') }}</strong>
            <ul class="mb-0">
              <li v-for="(warning, index) in duplicateWarnings" :key="index">
                {{ warning.message || warning }}
              </li>
            </ul>
          </div>
        </div>
      </div>

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
              <div class="row">
                <div class="col-12 col-lg-4">
                  <div
                    v-if="!aiDocument.files || aiDocument.files.length === 0"
                    class="text-muted"
                  >
                    {{ __('No files available') }}
                  </div>
                  <div class="list-group" v-else>
                    <button
                      v-for="file in aiDocument.files"
                      :key="file.id"
                      class="list-group-item list-group-item-action"
                      :class="{
                        active: selectedFile && selectedFile.id === file.id,
                      }"
                      type="button"
                      @click="selectFile(file)"
                    >
                      <i class="fa fa-fw fa-file me-2"></i>
                      {{ file.file_name }}
                    </button>
                  </div>
                </div>
                <div class="col-12 col-lg-8">
                  <div v-if="!selectedFile" class="text-muted">
                    {{ __('Select a file to preview') }}
                  </div>
                  <div v-else>
                    <div
                      class="d-flex justify-content-between align-items-center mb-2"
                    >
                      <h5 class="mb-0">{{ selectedFile.file_name }}</h5>
                      <a
                        class="btn btn-sm btn-outline-primary"
                        :href="downloadUrl(selectedFile)"
                      >
                        <i class="fa fa-fw fa-download"></i>
                        {{ __('Download') }}
                      </a>
                    </div>
                    <div
                      v-if="isImage(selectedFile)"
                      class="border rounded p-2"
                    >
                      <img :src="previewUrl(selectedFile)" class="img-fluid" />
                    </div>
                    <iframe
                      v-else
                      class="w-100 border rounded"
                      style="min-height: 500px"
                      :src="previewUrl(selectedFile)"
                    ></iframe>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="aiDocument.received_mail"
              class="tab-pane fade"
              id="document-tab-email"
              role="tabpanel"
              aria-labelledby="nav-document-tab-email"
              tabindex="0"
            >
              <div
                v-if="
                  !aiDocument.received_mail.html &&
                  !aiDocument.received_mail.text
                "
                class="text-muted"
              >
                {{ __('No email content available') }}
              </div>
              <div v-else class="card mb-3">
                <div
                  class="card-header"
                  v-if="
                    aiDocument.received_mail.html &&
                    aiDocument.received_mail.text
                  "
                >
                  <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                      <button
                        class="nav-link active"
                        id="nav-email-tab-html"
                        data-coreui-toggle="tab"
                        data-coreui-target="#email-tab-html"
                        type="button"
                        role="tab"
                        aria-controls="email-tab-html"
                        aria-selected="true"
                      >
                        {{ __('HTML view') }}
                      </button>
                    </li>
                    <li class="nav-item">
                      <button
                        class="nav-link"
                        id="nav-email-tab-text"
                        data-coreui-toggle="tab"
                        data-coreui-target="#email-tab-text"
                        type="button"
                        role="tab"
                        aria-controls="email-tab-text"
                        aria-selected="false"
                      >
                        {{ __('Text view') }}
                      </button>
                    </li>
                  </ul>
                </div>
                <div class="card-body">
                  <div class="tab-content" id="nav-tabContent">
                    <div
                      v-if="aiDocument.received_mail.html"
                      class="tab-pane fade"
                      :class="{
                        'show active':
                          !aiDocument.received_mail.text ||
                          aiDocument.received_mail.html,
                      }"
                      id="email-tab-html"
                      role="tabpanel"
                      aria-labelledby="nav-email-tab-html"
                      tabindex="0"
                      v-html="aiDocument.received_mail.html"
                    ></div>
                    <div
                      v-else
                      class="tab-pane fade show active"
                      id="email-tab-html"
                      role="tabpanel"
                      aria-labelledby="nav-email-tab-html"
                      tabindex="0"
                    >
                      <div class="text-muted">
                        {{ __('HTML content not available') }}
                      </div>
                    </div>
                    <div
                      v-if="aiDocument.received_mail.text"
                      class="tab-pane fade"
                      :class="{ 'show active': !aiDocument.received_mail.html }"
                      id="email-tab-text"
                      role="tabpanel"
                      aria-labelledby="nav-email-tab-text"
                      tabindex="0"
                    >
                      <pre>{{ aiDocument.received_mail.text }}</pre>
                    </div>
                    <div
                      v-else
                      class="tab-pane fade"
                      id="email-tab-text"
                      role="tabpanel"
                      aria-labelledby="nav-email-tab-text"
                      tabindex="0"
                    >
                      <div class="text-muted">
                        {{ __('Text content not available') }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

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
          </div>
        </div>
      </div>
    </div>

    <transaction-form-modal-standard :ai-document-id="aiDocument.id" />
    <transaction-form-modal-investment :ai-document-id="aiDocument.id" />
  </div>
</template>

<script setup>
  import { computed, ref } from 'vue';
  import { __ } from '../../helpers';
  import * as toastHelpers from '../../toast';
  import { storeNotification } from '../../handle_notifications';
  import TransactionFormModalStandard from '../TransactionForm/ModalStandard.vue';
  import TransactionFormModalInvestment from '../TransactionForm/ModalInvestment.vue';
  import Swal from 'sweetalert2';

  const aiDocument = ref(window.aiDocument || {});
  const statusLabels = window.aiDocumentStatusLabels || {};
  const sourceLabels = window.aiDocumentSourceLabels || {};
  const duplicateWarnings = ref(window.aiDocumentDuplicateWarnings || []);
  const selectedFile = ref(aiDocument.value.files?.[0] || null);
  const isBusy = ref(false);

  const createdAtLabel = computed(() => {
    if (!aiDocument.value.created_at) {
      return __('Not set');
    }

    return new Date(aiDocument.value.created_at).toLocaleString(
      window.YAFFA.locale,
    );
  });

  const processedAtLabel = computed(() => {
    if (!aiDocument.value.processed_at) {
      return __('Not set');
    }

    return new Date(aiDocument.value.processed_at).toLocaleString(
      window.YAFFA.locale,
    );
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

  const hasDraftData = computed(
    () => !!aiDocument.value.processed_transaction_data,
  );
  const draftData = computed(
    () => aiDocument.value.processed_transaction_data || {},
  );

  const standardTypeMap = {
    withdrawal: 'withdrawal',
    deposit: 'deposit',
    transfer: 'transfer',
  };

  const investmentTypeMap = {
    buy: 'Buy',
    sell: 'Sell',
    dividend: 'Dividend',
    interest: 'Interest yield',
    add_shares: 'Add shares',
    remove_shares: 'Remove shares',
  };

  const draftTypeLabel = computed(() => {
    const rawType = draftData.value?.raw?.transaction_type || '';
    if (draftData.value?.config_type === 'investment') {
      return investmentTypeMap[rawType] || rawType || __('Not set');
    }

    return standardTypeMap[rawType] || rawType || __('Not set');
  });

  const canFinalize = computed(
    () => aiDocument.value.status === 'ready_for_review' && hasDraftData.value,
  );

  const canReprocess = computed(() =>
    ['ready_for_review', 'processing_failed', 'finalized'].includes(
      aiDocument.value.status,
    ),
  );

  const transactionLink = computed(() =>
    aiDocument.value.transaction
      ? window.route('transaction.open', {
          transaction: aiDocument.value.transaction.id,
          action: 'show',
        })
      : '#',
  );

  const isImage = (file) => ['jpg', 'jpeg', 'png'].includes(file.file_type);

  const previewUrl = (file) =>
    window.route('ai-documents.files.show', {
      aiDocument: aiDocument.value.id,
      aiDocumentFile: file.id,
    });

  const downloadUrl = (file) => `${previewUrl(file)}?download=1`;

  const selectFile = (file) => {
    selectedFile.value = file;
  };

  const buildDraftTransaction = () => {
    const draft = JSON.parse(JSON.stringify(draftData.value || {}));
    draft.config = draft.config || {};
    draft.items = Array.isArray(draft.items) ? draft.items : [];

    const rawType = draft.raw?.transaction_type || '';
    let transactionTypeName = null;

    if (draft.config_type === 'investment') {
      transactionTypeName = investmentTypeMap[rawType] || 'Buy';
    } else {
      transactionTypeName = standardTypeMap[rawType] || 'withdrawal';
    }

    draft.transaction_type = {
      name: transactionTypeName,
    };

    return draft;
  };

  const finalizeDocument = () => {
    if (!hasDraftData.value) {
      toastHelpers.showErrorToast(__('No draft data available to finalize.'));
      return;
    }

    const draft = buildDraftTransaction();
    const itemCount = Array.isArray(draft.items) ? draft.items.length : 0;

    if (itemCount < 5) {
      window.dispatchEvent(
        new CustomEvent('initiateCreateFromDraft', {
          detail: {
            type: draft.config_type || 'standard',
            transaction: draft,
          },
        }),
      );
      return;
    }

    const form = window.document.createElement('form');
    form.setAttribute('method', 'POST');
    form.setAttribute('action', window.route('transactions.createFromDraft'));

    const csrfInput = window.document.createElement('input');
    csrfInput.setAttribute('type', 'hidden');
    csrfInput.setAttribute('name', '_token');
    csrfInput.setAttribute('value', window.csrfToken);
    form.appendChild(csrfInput);

    const transactionInput = window.document.createElement('input');
    transactionInput.setAttribute('type', 'hidden');
    transactionInput.setAttribute('name', 'transaction');
    transactionInput.setAttribute('value', JSON.stringify(draft));
    form.appendChild(transactionInput);

    const documentInput = window.document.createElement('input');
    documentInput.setAttribute('type', 'hidden');
    documentInput.setAttribute('name', 'ai_document_id');
    documentInput.setAttribute('value', aiDocument.value.id);
    form.appendChild(documentInput);

    window.document.body.appendChild(form);
    form.submit();
  };

  const reprocessDocument = () => {
    if (isBusy.value) {
      return;
    }

    isBusy.value = true;

    window.axios
      .post(
        window.route('api.documents.reprocess', {
          aiDocument: aiDocument.value.id,
        }),
      )
      .then((response) => {
        aiDocument.value.status = response.data.status;
        toastHelpers.showSuccessToast(response.data.message);
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
          window.route('api.documents.destroy', {
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
</script>
