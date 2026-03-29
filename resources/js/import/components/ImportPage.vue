<template>
  <div>
    <div class="row justify-content-center">
      <div class="col-12 col-xxl-10">
        <div class="row g-3 align-items-stretch mb-3">
          <div class="col-12 col-lg-6">
            <ImportSourceSelector
              v-model="sourceType"
              :accounts="accounts"
              :selected-account-id="selectedAccountId"
              :loading-accounts="loadingAccounts"
              @update:selectedAccountId="onAccountChange"
            />
          </div>
          <div class="col-12 col-lg-6">
            <ImportUploadCard
              ref="uploadCard"
              :source-type="sourceType"
              :account-id="selectedAccountId"
              :loading="loading"
              :progress="uploadProgress"
              :error="uploadError"
              @submit="onSubmit"
            />
          </div>
        </div>
      </div>
    </div>

    <div v-if="parseWarnings.length" class="alert alert-warning" role="alert">
      <div class="fw-semibold mb-1">{{ __('Parser warnings') }}</div>
      <ul class="mb-0 ps-3">
        <li
          v-for="(warning, index) in parseWarnings"
          :key="`parser-warning-${index}`"
        >
          {{ warning }}
        </li>
      </ul>
    </div>

    <ImportDraftTable :drafts="drafts" />
  </div>
</template>

<script>
  import axios from 'axios';
  import { onMounted, ref } from 'vue';
  import { __ } from '@/shared/lib/i18n';
  import ImportSourceSelector from './ImportSourceSelector.vue';
  import ImportUploadCard from './ImportUploadCard.vue';
  import ImportDraftTable from './ImportDraftTable.vue';

  export default {
    name: 'ImportPage',
    components: {
      ImportSourceSelector,
      ImportUploadCard,
      ImportDraftTable,
    },
    setup() {
      const sourceType = ref('qif');
      const selectedAccountId = ref('');
      const accounts = ref([]);
      const loadingAccounts = ref(false);

      const loading = ref(false);
      const uploadProgress = ref(0);
      const uploadError = ref(null);

      const drafts = ref([]);
      const parseWarnings = ref([]);

      const uploadCard = ref(null);

      const fetchAccounts = async () => {
        loadingAccounts.value = true;

        try {
          const response = await axios.get('/api/v1/accounts', {
            params: {
              limit: 0,
            },
          });

          accounts.value = Array.isArray(response.data) ? response.data : [];
        } catch (_error) {
          uploadError.value = __(
            'Unable to load accounts. Please refresh the page and try again.',
          );
        } finally {
          loadingAccounts.value = false;
        }
      };

      const resetParseState = () => {
        drafts.value = [];
        parseWarnings.value = [];
        uploadError.value = null;
        uploadProgress.value = 0;
      };

      const onAccountChange = (value) => {
        selectedAccountId.value = value;
        resetParseState();
      };

      const onSubmit = async ({ file }) => {
        uploadError.value = null;

        if (!file) {
          uploadError.value = __('Please select a file first.');
          return;
        }

        if (!selectedAccountId.value) {
          uploadError.value = __('Please select a target account first.');
          return;
        }

        if (sourceType.value === 'csv') {
          uploadError.value = __(
            'CSV import is not yet enabled in this milestone. Please upload a QIF file.',
          );
          return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('source_type', sourceType.value);
        formData.append('account_id', selectedAccountId.value);

        loading.value = true;
        uploadProgress.value = 0;

        try {
          const response = await axios.post('/api/v1/imports/parse', formData, {
            headers: {
              'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: (event) => {
              if (!event.total || event.total <= 0) {
                return;
              }

              const progress = Math.round((event.loaded * 100) / event.total);
              uploadProgress.value = Math.min(100, Math.max(0, progress));
            },
          });

          drafts.value = Array.isArray(response.data?.drafts)
            ? response.data.drafts
            : [];
          parseWarnings.value = Array.isArray(response.data?.warnings)
            ? response.data.warnings
            : [];

          if (
            uploadCard.value &&
            typeof uploadCard.value.reset === 'function'
          ) {
            uploadCard.value.reset();
          }
        } catch (error) {
          if (error?.response?.data?.errors) {
            const firstKey = Object.keys(error.response.data.errors)[0];
            if (
              firstKey &&
              Array.isArray(error.response.data.errors[firstKey])
            ) {
              uploadError.value = error.response.data.errors[firstKey][0];
            } else {
              uploadError.value = __(
                'Upload failed. Please review your input and try again.',
              );
            }
          } else if (error?.response?.data?.error?.message) {
            uploadError.value = error.response.data.error.message;
          } else {
            uploadError.value = __(
              'Upload failed due to a network or server error.',
            );
          }
        } finally {
          loading.value = false;
        }
      };

      onMounted(() => {
        fetchAccounts();
      });

      return {
        sourceType,
        selectedAccountId,
        accounts,
        loadingAccounts,
        loading,
        uploadProgress,
        uploadError,
        drafts,
        parseWarnings,
        uploadCard,
        onAccountChange,
        onSubmit,
      };
    },
  };
</script>
