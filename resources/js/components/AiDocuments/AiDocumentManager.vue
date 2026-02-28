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
        :status-options="statusLabels"
        :source-options="sourceLabels"
        @update="onFiltersUpdated"
      ></ai-document-filters>

      <date-range-filter-card
        :expanded="true"
        :show-update-button="false"
        component-id="aiDocumentDate"
        :initial-date-from="initialDateFrom"
        :initial-date-to="initialDateTo"
        :initial-preset="initialPreset"
        :update-url="true"
        @update="onDateRangeUpdated"
      ></date-range-filter-card>
    </div>
    <div class="col-12 col-lg-9">
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
  import OnboardingCard from '../Widgets/OnboardingCard.vue';
  import TransactionShowModal from '../TransactionDisplay/Modal.vue';
  import AiDocumentActions from './AiDocumentActions.vue';
  import AiDocumentFilters from './AiDocumentFilters.vue';
  import AiDocumentTable from './AiDocumentTable.vue';
  import AiDocumentUploadForm from './AiDocumentUploadForm.vue';
  import DateRangeFilterCard from '../DateRangeFilterCard.vue';
  import { __ } from '@/i18n';
  import * as toastHelpers from '@/toast';

  const documents = ref([]);
  const statusLabels = ref(window.aiDocumentStatusLabels || {});
  const sourceLabels = ref(window.aiDocumentSourceLabels || {});
  const tableRef = ref(null);
  const uploadFormRef = ref(null);
  const isLoading = ref(false);

  // Get initial date filters from URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  const initialDateFrom = ref(urlParams.get('date_from') || null);
  const initialDateTo = ref(urlParams.get('date_to') || null);
  const hasExplicitDateFilter = Boolean(
    initialDateFrom.value || initialDateTo.value,
  );
  const initialPreset = ref(
    urlParams.get('date_preset') ||
      (hasExplicitDateFilter ? null : 'previous90Days'),
  );

  const currentFilters = ref({
    status: '',
    source: '',
    search: '',
    dateFrom: initialDateFrom.value,
    dateTo: initialDateTo.value,
  });

  const onFiltersUpdated = (filters) => {
    currentFilters.value = { ...currentFilters.value, ...filters };
    tableRef.value?.applyFilters({
      status: currentFilters.value.status,
      source: currentFilters.value.source,
      search: currentFilters.value.search,
    });
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
          window.route('api.v1.documents.index'),
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

  const onDateRangeUpdated = async ({ dateFrom, dateTo }) => {
    currentFilters.value.dateFrom = dateFrom;
    currentFilters.value.dateTo = dateTo;

    await fetchDocuments({ dateFrom, dateTo });
  };

  const openUploadForm = () => {
    uploadFormRef.value?.show?.();
  };

  const onDocumentCreated = (data) => {
    // Refresh the documents list
    location.reload();
  };
</script>
