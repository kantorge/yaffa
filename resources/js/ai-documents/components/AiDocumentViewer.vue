<template>
  <div class="row" :class="{ 'pb-5': !actionsVisible }">
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
          id="cardOverview"
          class="collapse card-body show"
          aria-expanded="true"
        >
          <dl class="row mb-0">
            <dt class="col-7">{{ __('Status') }}</dt>
            <dd class="col-5">
              <span class="badge" :class="statusClass">
                {{ statusLabel }}
              </span>
            </dd>
            <dt class="col-7">{{ __('Source') }}</dt>
            <dd class="col-5">{{ sourceLabel }}</dd>
            <dt class="col-7">{{ __('Created at') }}</dt>
            <dd class="col-5" :title="aiDocument.created_at">
              {{ createdAtLabel }}
            </dd>
            <dt class="col-7">{{ __('Processed at') }}</dt>
            <dd
              class="col-5"
              :class="{ 'text-muted': !aiDocument.processed_at }"
            >
              {{ processedAtLabel }}
            </dd>
            <dt class="col-7">{{ __('Files') }}</dt>
            <dd class="col-5">
              {{ aiDocument.files?.length || 0 }}
            </dd>
            <dt class="col-7">{{ __('Linked transaction') }}</dt>
            <dd class="col-5">
              <a
                v-if="linkedTransactionId"
                href="#document-tab-transaction"
                :title="__('View transaction')"
                @click.prevent="showTransactionTab"
              >
                {{ linkedTransactionId }}
              </a>
              <span v-else class="text-muted">{{ __('Not available') }}</span>
            </dd>
          </dl>
        </div>
      </div>

      <div v-if="showExtractedDetails" class="card mb-3">
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
            v-if="showExtractedDetails"
            class="btn btn-sm btn-outline-primary"
            type="button"
            @click="showExtractedDetailsTab"
          >
            <i class="fa fa-fw fa-info-circle" :title="__('More details')"></i>
          </button>
        </div>
        <div
          id="cardExtractedData"
          class="collapse card-body show"
          aria-expanded="true"
        >
          <dl class="row mb-0">
            <dt class="col-6">{{ __('Transaction type') }}</dt>
            <dd
              class="col-6"
              :class="{
                'not-set': !draftTypeLabel,
              }"
            >
              {{ draftTypeLabel || unidentifiedLabel }}
            </dd>
            <dt class="col-6">{{ __('Date') }}</dt>
            <dd
              class="col-6"
              :class="{
                'not-set': isUnidentified(draftData.date),
              }"
            >
              {{ formatRawValue(draftData.date) }}
            </dd>

            <template v-if="isInvestment">
              <dt class="col-6">{{ __('Account') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'not-set': isUnidentified(rawData.account),
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
                  'not-set': isUnidentified(rawData.investment),
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
                  'not-set': isUnidentified(rawData.quantity),
                }"
              >
                {{ formatRawValue(rawData.quantity) }}
              </dd>
              <dt class="col-6">{{ __('Price') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'not-set': isUnidentified(rawData.price),
                }"
              >
                {{ formatRawValue(rawData.price) }}
              </dd>
              <dt class="col-6">{{ __('Amount') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'not-set': isUnidentified(rawData.amount),
                }"
              >
                {{ formatRawValue(rawData.amount) }}
              </dd>
              <dt class="col-6">{{ __('Currency') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'not-set': isUnidentified(rawData.currency),
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
                    'not-set': isUnidentified(rawData.account_from),
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
                    'not-set': isUnidentified(rawData.account_to),
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
                    'not-set': isUnidentified(rawData.account),
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
                    'not-set':
                      isUnidentified(rawData.payee) &&
                      !matchedEntities.payee?.matched,
                  }"
                >
                  <template v-if="matchedEntities.payee?.matched">
                    <a
                      v-if="matchedEntities.payee.url"
                      :href="matchedEntities.payee.url"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      {{ matchedEntities.payee.name }}
                    </a>
                    <span v-else>{{ matchedEntities.payee.name }}</span>
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
                  'not-set': isUnidentified(rawData.amount),
                }"
              >
                {{ formatRawValue(rawData.amount) }}
              </dd>
              <dt class="col-6">{{ __('Currency') }}</dt>
              <dd
                class="col-6"
                :class="{
                  'not-set': isUnidentified(rawData.currency),
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
        :currency="draftCurrency"
        :draft-amount="draftData.config?.amount_from ?? rawData.amount"
      />

      <div ref="actionsCard" class="card mb-3">
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
          id="cardActions"
          class="list-group list-group-flush collapse show"
          aria-expanded="true"
        >
          <li
            v-for="action in actions"
            :key="action.key"
            class="list-group-item d-flex justify-content-between align-items-center"
          >
            {{ action.label }}
            <button
              class="btn btn-xs"
              :class="`btn-${action.variant}`"
              type="button"
              :disabled="action.busyAware && isBusy"
              :title="action.title || action.label"
              @click="action.handler"
            >
              <i
                class="fa fa-fw"
                :class="[action.icon, { 'fa-spin': action.spin && isBusy }]"
              ></i>
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
                id="nav-document-tab-files"
                class="nav-link active"
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
            <li v-if="aiDocument.received_mail" class="nav-item">
              <button
                id="nav-document-tab-email"
                class="nav-link"
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
            <li v-if="aiDocument.custom_prompt" class="nav-item">
              <button
                id="nav-document-tab-prompt"
                class="nav-link"
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
            <li v-if="showExtractedDetails" class="nav-item">
              <button
                id="nav-document-tab-extracted"
                class="nav-link"
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
            <li v-if="hasProcessingHistory" class="nav-item">
              <button
                id="nav-document-tab-history"
                class="nav-link"
                data-coreui-toggle="tab"
                data-coreui-target="#document-tab-history"
                type="button"
                role="tab"
                aria-controls="document-tab-history"
                aria-selected="false"
              >
                {{ __('Processing details') }}
              </button>
            </li>
            <li v-if="linkedTransactionId" class="nav-item">
              <button
                id="nav-document-tab-transaction"
                class="nav-link"
                data-coreui-toggle="tab"
                data-coreui-target="#document-tab-transaction"
                type="button"
                role="tab"
                aria-controls="document-tab-transaction"
                aria-selected="false"
              >
                {{ __('Transaction') }}
              </button>
            </li>
          </ul>
        </div>
        <div class="card-body">
          <div id="document-tab-content" class="tab-content">
            <div
              id="document-tab-files"
              class="tab-pane fade show active"
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
              id="document-tab-prompt"
              class="tab-pane fade"
              role="tabpanel"
              aria-labelledby="nav-document-tab-prompt"
              tabindex="0"
            >
              <pre class="mb-0">{{ aiDocument.custom_prompt }}</pre>
            </div>

            <div
              v-if="showExtractedDetails"
              id="document-tab-extracted"
              class="tab-pane fade"
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
              id="document-tab-history"
              class="tab-pane fade"
              role="tabpanel"
              aria-labelledby="nav-document-tab-history"
              tabindex="0"
            >
              <ai-document-processing-history
                :entries="processingHistory"
                :auto-record-note="autoRecordNote"
              />
            </div>

            <div
              v-if="linkedTransactionId"
              id="document-tab-transaction"
              class="tab-pane fade"
              role="tabpanel"
              aria-labelledby="nav-document-tab-transaction"
              tabindex="0"
            >
              <div v-if="transactionLoading" class="text-center text-muted">
                <i class="fa fa-spinner fa-spin"></i> {{ __('Loading') }}
              </div>
              <div v-else-if="transactionError" class="text-danger">
                {{ transactionError }}
              </div>
              <TransactionShowStandard
                v-else-if="linkedTransaction?.config_type === 'standard'"
                :transaction="linkedTransaction"
              />
              <TransactionShowInvestment
                v-else-if="linkedTransaction?.config_type === 'investment'"
                :transaction="linkedTransaction"
              />
              <div v-else class="text-muted">{{ __('Not available') }}</div>
              <div
                v-if="
                  linkedTransaction?.id &&
                  !transactionLoading &&
                  !transactionError
                "
                class="card mb-0"
              >
                <div class="card-body">
                  <ActionButtonBar
                    :transaction="linkedTransaction"
                    :is-modal="false"
                    edit-callback="back"
                    :on-deleted="handleLinkedTransactionDeleted"
                    @transaction-updated="(t) => (linkedTransaction = t)"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile only: the Actions card's actions, pinned to the bottom once the card scrolls out of view -->
    <div
      v-if="!actionsVisible && actions.length > 0"
      class="position-fixed bottom-0 start-0 end-0 d-flex gap-2 p-2 bg-body border-top shadow d-lg-none sticky-action-bar"
    >
      <button
        v-for="action in actions"
        :key="action.key"
        class="btn flex-fill"
        :class="`btn-${action.variant}`"
        type="button"
        :disabled="action.busyAware && isBusy"
        :title="action.title || action.label"
        :aria-label="action.title || action.label"
        @click="action.handler"
      >
        <i
          class="fa fa-fw"
          :class="[action.icon, { 'fa-spin': action.spin && isBusy }]"
        ></i>
      </button>
    </div>

    <transaction-form-modal-standard :ai-document-id="aiDocument.id" />
    <transaction-form-modal-investment :ai-document-id="aiDocument.id" />
    <transaction-show-modal />
  </div>
</template>

<script setup>
  import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
  } from 'vue';
  import {
    getTransactionTypeConfig,
    processTransaction,
  } from '@/shared/lib/helpers';
  import {
    __,
    toFormattedCurrency,
    toFormattedDate,
    toFormattedDateTime,
  } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';
  import { storeNotification } from '@/shared/lib/notifications/handleNotifications';
  import TransactionFormModalStandard from '@/transactions/components/form/ModalStandard.vue';
  import TransactionFormModalInvestment from '@/transactions/components/form/ModalInvestment.vue';
  import TransactionShowModal from '@/transactions/components/display/Modal.vue';
  import TransactionShowStandard from '@/transactions/components/display/ShowStandard.vue';
  import ActionButtonBar from '@/transactions/components/display/ActionButtonBar.vue';
  import TransactionShowInvestment from '@/transactions/components/display/ShowInvestment.vue';
  import AiDocumentEmailViewer from './AiDocumentEmailViewer.vue';
  import AiDocumentFileViewer from './AiDocumentFileViewer.vue';
  import AiDocumentExtractedDetails from './AiDocumentExtractedDetails.vue';
  import AiDocumentDuplicates from './AiDocumentDuplicates.vue';
  import AiDocumentProcessingHistory from './AiDocumentProcessingHistory.vue';
  import Swal from 'sweetalert2';
  import { confirmDelete } from '@/shared/lib/confirm';

  const aiDocument = ref(window.aiDocument || {});
  const statusLabels = window.aiDocumentStatusLabels || {};
  const sourceLabels = window.aiDocumentSourceLabels || {};
  const isBusy = ref(false);
  const route = window.route;
  const locale = window.YAFFA.userSettings.locale || 'en';
  const unidentifiedLabel = __('Unidentified');
  const dateTimeNoSeconds = {
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: 'numeric',
    minute: 'numeric',
  };

  // Computed properties used in the component
  const createdAtLabel = computed(() => {
    if (!aiDocument.value.created_at) {
      return __('Not set');
    }

    return toFormattedDateTime(
      aiDocument.value.created_at,
      locale,
      '',
      dateTimeNoSeconds,
    );
  });

  const processedAtLabel = computed(() => {
    if (!aiDocument.value.processed_at) {
      return __('Not set');
    }

    return toFormattedDateTime(
      aiDocument.value.processed_at,
      locale,
      '',
      dateTimeNoSeconds,
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
      case 'awaiting_itemization':
        return 'bg-warning';
      case 'finalized':
      case 'auto_recorded':
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

  // A document dismissed before it was processed has no extraction to show (only the dismissal note)
  const showExtractedDetails = computed(
    () =>
      hasDraftData.value &&
      (['ready_for_review', 'finalized'].includes(aiDocument.value.status) ||
        (aiDocument.value.status === 'dismissed' &&
          !!(draftData.value.raw || draftData.value.config_type))),
  );

  const processingHistory = computed(
    () => aiDocument.value.ai_chat_history || [],
  );

  const hasProcessingHistory = computed(
    () =>
      (showExtractedDetails.value &&
        Array.isArray(processingHistory.value) &&
        processingHistory.value.length > 0) ||
      !!autoRecordNote.value,
  );

  // Documents linked to a transaction cannot be reprocessed, see App\Enums\AiDocumentStatus::reprocessable()
  const canReprocess = computed(() =>
    ['ready_for_review', 'processing_failed', 'dismissed'].includes(
      aiDocument.value.status,
    ),
  );

  const canDismiss = computed(() =>
    ['ready_for_review', 'processing_failed'].includes(aiDocument.value.status),
  );

  // The transaction the document created, or the one it was closed against as a duplicate
  const linkedTransactionId = computed(
    () =>
      aiDocument.value.transaction?.id ??
      aiDocument.value.origins?.find(
        (origin) => origin.relation === 'duplicate_of',
      )?.transaction_id ??
      null,
  );

  const linkedTransaction = ref(null);
  const transactionLoading = ref(false);
  const transactionError = ref('');

  const loadLinkedTransaction = () => {
    if (!linkedTransactionId.value) {
      return;
    }

    transactionLoading.value = true;
    transactionError.value = '';

    window.axios
      .get(
        route('api.v1.transactions.show', {
          transaction: linkedTransactionId.value,
        }),
      )
      .then((response) => {
        linkedTransaction.value = processTransaction(response.data.transaction);
      })
      .catch((error) => {
        transactionError.value = __(
          'Error while loading the transaction: :errorMessage',
          { errorMessage: error.response?.data?.message || error.message },
        );
      })
      .finally(() => {
        transactionLoading.value = false;
      });
  };

  const handleLinkedTransactionDeleted = () => {
    linkedTransaction.value = null;
    showFilesTab();
    refreshDocument();
  };

  // An auto-recorded transaction still waiting for the person to confirm it
  const unreviewedCreatedOrigin = computed(
    () =>
      aiDocument.value.origins?.find(
        (origin) => origin.relation === 'created' && !origin.reviewed_at,
      ) ?? null,
  );

  const markTransactionChecked = () => {
    const origin = unreviewedCreatedOrigin.value;

    isBusy.value = true;

    window.axios
      .post(route('api.v1.transaction-origins.review', { origin: origin.id }))
      .then(() => {
        toastHelpers.showSuccessToast(__('Transaction marked as checked'));
        handleOriginReviewed({ detail: { id: origin.id } });
      })
      .catch((error) => {
        toastHelpers.showErrorToast(
          __('Error while reviewing document: :errorMessage', {
            errorMessage: error.response?.data?.message || error.message,
          }),
        );
      })
      .finally(() => {
        isBusy.value = false;
      });
  };

  // Fired by the transaction details (or the button above) once an origin is reviewed
  const handleOriginReviewed = (event) => {
    const reviewedAt = new Date().toISOString();

    [aiDocument.value.origins, linkedTransaction.value?.origins].forEach(
      (origins) =>
        origins?.forEach((origin) => {
          if (origin.id === event.detail.id && !origin.reviewed_at) {
            origin.reviewed_at = reviewedAt;
          }
        }),
    );
  };

  // The document's currency with the user's rounding settings, for formatting candidate amounts
  const draftCurrency = computed(() => {
    const iso = String(rawData.value.currency || '').toLowerCase();

    return iso
      ? (window.aiDocumentCurrencies || []).find(
          (c) => c.iso_code.toLowerCase() === iso,
        ) || null
      : null;
  });

  // Why the document was (not) recorded automatically, see App\Services\AutoRecord\GateEvaluator
  const autoRecordNote = computed(() => {
    const document = aiDocument.value;

    if (document.status === 'auto_recorded') {
      const reason = document.origins?.find(
        (origin) => origin.relation === 'created',
      )?.decision_reason;

      return reason || __('Recorded automatically');
    }

    if (document.status === 'dismissed') {
      const reason = document.processed_transaction_data?.dismissal?.reason;

      if (reason === 'non_financial') {
        return __(
          'Dismissed automatically: the document was recognized as not financial.',
        );
      }

      return reason === 'manual' ? __('Dismissed manually.') : null;
    }

    const autoRecord = document.processed_transaction_data?.auto_record;

    if (document.status === 'awaiting_itemization') {
      return autoRecord?.hold_until
        ? __('Waiting for the receipt until :date.', {
            date: toFormattedDate(
              new Date(autoRecord.hold_until),
              locale,
              null,
            ),
          })
        : __('Waiting for the receipt.');
    }

    if (document.status === 'ready_for_review' && autoRecord?.reason) {
      const { amount, median, from, to } = autoRecord.context || {};
      const currency = rawData.value.currency;

      // Show the amounts in the document's currency, rounded as the user's currency (if known) is configured
      if (
        autoRecord.failed_gate === 'amount' &&
        amount &&
        median &&
        from &&
        to &&
        currency
      ) {
        const currencySettings = {
          generic_decimal_precision: null,
          ...(window.aiDocumentCurrencies || []).find(
            (c) => c.iso_code.toLowerCase() === String(currency).toLowerCase(),
          ),
          iso_code: String(currency).toUpperCase(),
        };

        return __('Not recorded automatically: :reason', {
          reason: __(
            'Amount: :amount is outside the usual range (around :median, accepted :from - :to).',
            {
              amount: toFormattedCurrency(amount, locale, currencySettings),
              median: toFormattedCurrency(median, locale, currencySettings),
              from: toFormattedCurrency(from, locale, currencySettings),
              to: toFormattedCurrency(to, locale, currencySettings),
            },
          ),
        });
      }

      return __('Not recorded automatically: :reason', {
        reason: autoRecord.reason,
      });
    }

    return null;
  });

  const actions = computed(() =>
    [
      {
        key: 'finalize',
        show: canFinalize.value,
        label: __('Finalize transaction'),
        icon: 'fa-edit',
        variant: 'primary',
        handler: finalizeDocument,
      },
      {
        key: 'check',
        show: !!unreviewedCreatedOrigin.value,
        label: __('Mark transaction as checked'),
        icon: 'fa-check',
        variant: 'success',
        busyAware: true,
        handler: markTransactionChecked,
      },
      {
        key: 'reprocess',
        show: canReprocess.value,
        label: __('Reprocess document'),
        icon: 'fa-repeat',
        variant: 'warning',
        busyAware: true,
        spin: true,
        handler: reprocessDocument,
      },
      {
        key: 'dismiss',
        show: canDismiss.value,
        label: __('Dismiss document'),
        icon: 'fa-ban',
        variant: 'secondary',
        busyAware: true,
        handler: dismissDocument,
      },
      {
        key: 'delete',
        show: true,
        label: __('Delete document'),
        title: __('Delete'),
        icon: 'fa-trash',
        variant: 'danger',
        busyAware: true,
        handler: deleteDocument,
      },
    ].filter((action) => action.show),
  );

  // The sticky action bar appears only while the Actions card is out of the viewport
  const actionsCard = useTemplateRef('actionsCard');
  const actionsVisible = ref(true);
  let actionsObserver = null;

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

  const shouldOpenStandaloneFinalizeView = (draft) => {
    if (draft.config_type !== 'standard') {
      return false;
    }

    return (
      Array.isArray(draft.transaction_items) &&
      draft.transaction_items.length > 3
    );
  };

  const submitDraftToStandaloneFinalizeView = (draft) => {
    const csrfToken =
      window.csrfToken ||
      window.document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (!csrfToken) {
      toastHelpers.showErrorToast(__('Unable to open finalize view.'));
      return;
    }

    const form = window.document.createElement('form');
    form.method = 'POST';

    const callbackValue =
      new URLSearchParams(window.location.search).get('callback') || 'back';
    const targetUrl = new URL(
      route('transactions.createFromDraft'),
      window.location.origin,
    );
    targetUrl.searchParams.set('callback', callbackValue);

    form.action = targetUrl.toString();
    form.style.display = 'none';

    const addHiddenField = (name, value) => {
      const input = window.document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    };

    addHiddenField('_token', csrfToken);
    addHiddenField('transaction', JSON.stringify(draft));

    if (aiDocument.value.id) {
      addHiddenField('ai_document_id', String(aiDocument.value.id));
    }

    window.document.body.appendChild(form);
    form.submit();
  };

  const finalizeDocument = () => {
    // The button should be disabled if there's no draft data, but we add a check here just in case
    if (!hasDraftData.value) {
      toastHelpers.showErrorToast(__('No draft data available to finalize.'));
      return;
    }

    const draft = buildDraftTransaction();

    if (shouldOpenStandaloneFinalizeView(draft)) {
      submitDraftToStandaloneFinalizeView(draft);
      return;
    }

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
        cancelButton: 'btn btn-secondary ms-3',
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

  const dismissDocument = () => {
    if (isBusy.value) {
      return;
    }

    isBusy.value = true;

    window.axios
      .post(
        window.route('api.v1.documents.dismiss', {
          aiDocument: aiDocument.value.id,
        }),
      )
      .then((response) => {
        aiDocument.value.status = response.data.status;
        aiDocument.value.processed_transaction_data = {
          ...aiDocument.value.processed_transaction_data,
          dismissal: { reason: 'manual' },
        };
        toastHelpers.showSuccessToast(response.data.message);
      })
      .catch((error) => {
        toastHelpers.showErrorToast(
          __('Error while dismissing document: :errorMessage', {
            errorMessage: error.response?.data?.error || error.message,
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

    confirmDelete(__('Are you sure you want to delete this document?'), {
      confirmButtonText: __('Delete'),
    }).then((result) => {
      if (!result.isConfirmed) {
        isBusy.value = false;
        return;
      }

      window.axios
        .delete(
          route('api.v1.documents.destroy', {
            aiDocument: aiDocument.value.id,
          }),
        )
        .then(() => {
          storeNotification('success', __('Document deleted'), {
            dismissible: true,
          });
          window.location.href = route('ai-documents.index');
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

  // On narrow screens the tabs are below the cards, so bring them into view as well
  const showTransactionTab = () => {
    const button = window.document.getElementById(
      'nav-document-tab-transaction',
    );

    new window.coreui.Tab(button).show();
    button.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
        route('api.v1.documents.show', {
          aiDocument: aiDocument.value.id,
        }),
      )
      .then((response) => {
        if (response?.data?.document) {
          aiDocument.value = response.data.document;
          loadLinkedTransaction();
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

    // Read the draft's payee before the refresh replaces the document
    const aliasOffer = getPayeeAliasOffer(transaction);

    refreshDocument();

    if (aliasOffer) {
      offerPayeeAlias(aliasOffer);
    }
  };

  // The leading words of the extracted payee text, up to the first word with a digit (store numbers, cities
  // and the like follow it), at most three words
  const leadingWords = (text) => {
    const words = String(text || '')
      .trim()
      .split(/\s+/);
    const firstNumbered = words.findIndex((word) => /\d/.test(word));

    return words
      .slice(0, firstNumbered === -1 ? words.length : firstNumbered)
      .slice(0, 3)
      .join(' ');
  };

  // When the person picked another payee than the one the document was matched to, the extracted text is
  // worth remembering as an alias of the chosen payee
  const getPayeeAliasOffer = (transaction) => {
    const payeeId =
      {
        withdrawal: transaction.config?.account_to_id,
        deposit: transaction.config?.account_from_id,
      }[transaction.transaction_type] ?? null;
    const alias = leadingWords(rawData.value.payee);

    if (!payeeId || !alias || payeeId === matchedEntities.value.payee?.id) {
      return null;
    }

    return { payeeId, alias };
  };

  const offerPayeeAlias = ({ payeeId, alias }) => {
    window.axios
      .get(route('api.v1.payees.show', { accountEntity: payeeId }))
      .then(({ data: payee }) => {
        const knownNames = [payee.name, ...(payee.alias || '').split(/\r?\n/)];

        if (
          knownNames.some(
            (name) => name.trim().toLowerCase() === alias.toLowerCase(),
          )
        ) {
          return null;
        }

        return Swal.fire({
          text: __(
            'Add ":alias" as an alias of :payee? Documents with this payee text will then be matched to it.',
            { alias, payee: payee.name },
          ),
          icon: 'question',
          showCancelButton: true,
          cancelButtonText: __('No'),
          confirmButtonText: __('Add alias'),
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-secondary ms-3',
          },
        }).then((result) => {
          if (!result.isConfirmed) {
            return null;
          }

          return window.axios
            .patch(route('api.v1.payees.update', { accountEntity: payeeId }), {
              config_type: 'payee',
              name: payee.name,
              active: payee.active,
              alias: [payee.alias, alias].filter(Boolean).join('\n'),
              simplified: true,
              config: { category_id: payee.config?.category_id ?? null },
            })
            .then(() => {
              toastHelpers.showSuccessToast(__('Alias added'));
            });
        });
      })
      .catch((error) => {
        toastHelpers.showErrorToast(
          error.response?.data?.errors?.alias?.[0] ||
            __('Unable to add the alias.'),
        );
      });
  };

  onMounted(() => {
    window.addEventListener('transaction-created', handleTransactionCreated);
    window.addEventListener('transactionOriginReviewed', handleOriginReviewed);
    loadLinkedTransaction();

    actionsObserver = new IntersectionObserver(([entry]) => {
      actionsVisible.value = entry.isIntersecting;
    });
    actionsObserver.observe(actionsCard.value);

    // Set by the document list's "Finalize transaction" action
    if (
      new URLSearchParams(window.location.search).has('finalize') &&
      canFinalize.value
    ) {
      finalizeDocument();
    }
  });

  onBeforeUnmount(() => {
    actionsObserver?.disconnect();
    window.removeEventListener('transaction-created', handleTransactionCreated);
    window.removeEventListener(
      'transactionOriginReviewed',
      handleOriginReviewed,
    );
  });
</script>
