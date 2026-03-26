<template>
  <div class="row">
    <div class="col-12 col-lg-3">
      <onboarding-card
        :card-title="__('Guided tour')"
        :completed-message="
          __('You can dismiss this widget to hide it forever.')
        "
        topic="AiDocuments"
      ></onboarding-card>

      <ai-document-actions
        @open-upload-form="openUploadForm"
      ></ai-document-actions>

      <ai-document-filters
        :initial-status="initialStatus"
        :initial-source="initialSource"
        :initial-search="initialSearch"
        :status-options="statusLabels"
        :source-options="sourceLabels"
        @update="onFiltersUpdated"
      ></ai-document-filters>

      <date-range-filter-card
        :expanded="true"
        :show-update-button="false"
        :title="__('Received date')"
        component-id="aiDocumentDate"
        :initial-date-from="initialDateFrom"
        :initial-date-to="initialDateTo"
        :initial-preset="initialPreset"
        :update-url="false"
        @update="onDateRangeUpdated"
      ></date-range-filter-card>

      <date-range-filter-card
        :expanded="false"
        :show-update-button="false"
        :title="__('Detected transaction date')"
        component-id="aiDocumentDetectedDate"
        :initial-date-from="initialDetectedDateFrom"
        :initial-date-to="initialDetectedDateTo"
        :initial-preset="initialDetectedDatePreset"
        :update-url="false"
        @update="onDetectedDateRangeUpdated"
      ></date-range-filter-card>
    </div>
    <div class="col-12 col-lg-9">
      <div
        v-if="!aiProcessingEnabled"
        class="alert alert-warning d-flex justify-content-between align-items-center"
        role="alert"
      >
        <div>
          <i class="fa fa-exclamation-triangle me-1"></i>
          {{
            __(
              'AI processing is currently disabled. Uploaded or imported documents will not be processed until it is enabled.',
            )
          }}
        </div>
        <a class="btn btn-sm btn-outline-warning" :href="aiSettingsUrl">
          {{ __('Open AI settings') }}
        </a>
      </div>

      <ai-document-table
        ref="tableRef"
        :documents="documents"
        :status-labels="statusLabels"
        :source-labels="sourceLabels"
      ></ai-document-table>

      <transaction-show-modal></transaction-show-modal>
    </div>

    <!-- Upload Form Modal -->
    <ai-document-upload-form
      ref="uploadFormRef"
      @document-created="onDocumentCreated"
    ></ai-document-upload-form>
  </div>
</template>

<script setup>
  import { nextTick, ref } from 'vue';
  import OnboardingCard from '@/dashboard/components/widgets/OnboardingCard.vue';
  import TransactionShowModal from '@/transactions/components/display/Modal.vue';
  import AiDocumentActions from './AiDocumentActions.vue';
  import AiDocumentFilters from './AiDocumentFilters.vue';
  import AiDocumentTable from './AiDocumentTable.vue';
  import AiDocumentUploadForm from './AiDocumentUploadForm.vue';
  import DateRangeFilterCard from '@/shared/ui/date/DateRangeFilterCard.vue';
  import { __ } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';

  const documents = ref([]);
  const statusLabels = ref(window.aiDocumentStatusLabels || {});
  const sourceLabels = ref(window.aiDocumentSourceLabels || {});
  const tableRef = ref(null);
  const uploadFormRef = ref(null);
  const isLoading = ref(false);
  const route = window.route;
  const aiProcessingEnabled = ref(
    window.aiDocumentConfig?.aiProcessingEnabled ?? true,
  );
  const aiSettingsUrl = ref(
    window.aiDocumentConfig?.aiSettingsUrl || route('user.ai-settings'),
  );

  // Get initial date filters from URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  const initialDateFrom = ref(urlParams.get('date_from') || null);
  const initialDateTo = ref(urlParams.get('date_to') || null);
  const initialStatus = ref(urlParams.get('status') || 'ready_for_review');
  const initialSource = ref(urlParams.get('source') || '');
  const initialSearch = ref(urlParams.get('search') || '');
  const hasExplicitDateFilter = Boolean(
    initialDateFrom.value || initialDateTo.value,
  );
  const initialPreset = ref(
    urlParams.get('date_preset') ||
      (hasExplicitDateFilter ? null : 'previous90Days'),
  );
  const initialDetectedDateFrom = ref(
    urlParams.get('detected_date_from') || null,
  );
  const initialDetectedDateTo = ref(urlParams.get('detected_date_to') || null);
  const initialDetectedDatePreset = ref(
    urlParams.get('detected_date_preset') || null,
  );

  const currentFilters = ref({
    status: initialStatus.value,
    source: initialSource.value,
    search: initialSearch.value,
    dateFrom: initialDateFrom.value,
    dateTo: initialDateTo.value,
    datePreset: initialPreset.value,
    detectedDateFrom: initialDetectedDateFrom.value,
    detectedDateTo: initialDetectedDateTo.value,
    detectedDatePreset: initialDetectedDatePreset.value,
  });

  const rebuildUrl = () => {
    const params = new URLSearchParams();

    if (currentFilters.value.status) {
      params.set('status', currentFilters.value.status);
    }

    if (currentFilters.value.source) {
      params.set('source', currentFilters.value.source);
    }

    if (currentFilters.value.search) {
      params.set('search', currentFilters.value.search);
    }

    if (currentFilters.value.datePreset) {
      params.set('date_preset', currentFilters.value.datePreset);
    } else {
      if (currentFilters.value.dateFrom) {
        params.set('date_from', currentFilters.value.dateFrom);
      }

      if (currentFilters.value.dateTo) {
        params.set('date_to', currentFilters.value.dateTo);
      }
    }

    if (currentFilters.value.detectedDatePreset) {
      params.set(
        'detected_date_preset',
        currentFilters.value.detectedDatePreset,
      );
    } else {
      if (currentFilters.value.detectedDateFrom) {
        params.set('detected_date_from', currentFilters.value.detectedDateFrom);
      }

      if (currentFilters.value.detectedDateTo) {
        params.set('detected_date_to', currentFilters.value.detectedDateTo);
      }
    }

    const query = params.toString();
    const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}`;
    window.history.pushState('', '', nextUrl);
  };

  const onFiltersUpdated = (filters) => {
    currentFilters.value = { ...currentFilters.value, ...filters };
    tableRef.value?.applyFilters({
      status: currentFilters.value.status,
      source: currentFilters.value.source,
      search: currentFilters.value.search,
      detectedDateFrom: currentFilters.value.detectedDateFrom,
      detectedDateTo: currentFilters.value.detectedDateTo,
    });
    rebuildUrl();
  };

  const normalizeDocuments = (apiDocuments) =>
    apiDocuments.map((document) => ({
      ...document,
      files: document.files || document.ai_document_files || [],
    }));

  const fetchDocuments = async ({ dateFrom, dateTo }) => {
    if (isLoading.value) {
      return;
    }

    isLoading.value = true;

    try {
      const allDocuments = [];
      const perPage = 100;
      let page = 1;
      let lastPage = 1;

      do {
        const response = await window.axios.get(
          route('api.v1.documents.index'),
          {
            params: {
              page,
              per_page: perPage,
              date_from: dateFrom || undefined,
              date_to: dateTo || undefined,
            },
          },
        );

        allDocuments.push(...(response.data?.data || []));
        lastPage = response.data?.meta?.last_page || 1;
        page += 1;
      } while (page <= lastPage);

      documents.value = normalizeDocuments(allDocuments);

      await nextTick();
      tableRef.value?.applyFilters({
        status: currentFilters.value.status,
        source: currentFilters.value.source,
        search: currentFilters.value.search,
        detectedDateFrom: currentFilters.value.detectedDateFrom,
        detectedDateTo: currentFilters.value.detectedDateTo,
      });
    } catch (error) {
      documents.value = [];
      toastHelpers.showErrorToast(
        __('Error while loading AI documents: :errorMessage', {
          errorMessage: error.response?.data?.message || error.message,
        }),
      );
    } finally {
      isLoading.value = false;
    }
  };

  const onDateRangeUpdated = async ({ dateFrom, dateTo, preset }) => {
    currentFilters.value.dateFrom = dateFrom;
    currentFilters.value.dateTo = dateTo;
    currentFilters.value.datePreset = preset || null;
    rebuildUrl();

    await fetchDocuments({ dateFrom, dateTo });
  };

  const onDetectedDateRangeUpdated = ({ dateFrom, dateTo, preset }) => {
    currentFilters.value.detectedDateFrom = dateFrom;
    currentFilters.value.detectedDateTo = dateTo;
    currentFilters.value.detectedDatePreset = preset || null;

    tableRef.value?.applyFilters({
      status: currentFilters.value.status,
      source: currentFilters.value.source,
      search: currentFilters.value.search,
      detectedDateFrom: currentFilters.value.detectedDateFrom,
      detectedDateTo: currentFilters.value.detectedDateTo,
    });

    rebuildUrl();
  };

  const openUploadForm = () => {
    uploadFormRef.value?.show?.();
  };

  const onDocumentCreated = async () => {
    await fetchDocuments({
      dateFrom: currentFilters.value.dateFrom,
      dateTo: currentFilters.value.dateTo,
    });
  };
</script>
