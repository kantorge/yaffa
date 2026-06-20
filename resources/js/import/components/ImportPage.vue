<template>
  <div>
    <div class="row justify-content-center">
      <div class="col-12 col-xxl-10">
        <div class="card mb-3">
          <div class="card-header">
            <div class="card-title">{{ __('Import file') }}</div>
          </div>
          <div class="card-body">
            <ImportSourceSelector
              v-model="sourceType"
              :accounts="accounts"
              :selected-account-id="selectedAccountId"
              :loading-accounts="loadingAccounts"
              :disabled="loading"
              @update:selectedAccountId="onAccountChange"
            />
            <hr class="my-3" />
            <ImportUploadCard
              ref="uploadCard"
              :source-type="sourceType"
              :account-id="selectedAccountId"
              :profiles="profiles"
              :loading-profiles="loadingProfiles"
              :selected-profile-id="selectedProfileId"
              :qif-profiles="qifProfiles"
              :loading-qif-profiles="loadingQifProfiles"
              :selected-qif-profile-id="selectedQifProfileId"
              :loading="loading"
              :progress="uploadProgress"
              :error="uploadError"
              @submit="onSubmit"
              @update:selectedProfileId="onProfileChange"
              @update:selectedQifProfileId="onQifProfileChange"
            />
          </div>
        </div>
      </div>
    </div>

    <div class="row justify-content-center mt-3">
      <div class="col-12 col-xxl-10">
        <div
          v-if="parseWarnings.length"
          class="alert alert-warning alert-dismissible"
          role="alert"
        >
          <button
            type="button"
            class="btn-close"
            :aria-label="__('Close')"
            @click="parseWarnings = []"
          ></button>
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

        <ImportDraftTable
          :drafts="drafts"
          :account-currency="accountCurrency"
          :class="parseWarnings.length ? 'mt-3' : ''"
          @ignore-draft="onIgnoreDraft"
          @finalize-draft="onFinalizeDraft"
        />

        <FileImportProfileManager
          v-if="sourceType === 'csv'"
          :profiles="profiles"
          :loading="loadingProfiles"
          file-type="csv"
          class="mt-3"
          @profiles-updated="fetchProfiles"
        />
        <FileImportProfileManager
          v-if="sourceType === 'qif'"
          :profiles="qifProfiles"
          :loading="loadingQifProfiles"
          file-type="qif"
          class="mt-3"
          @profiles-updated="fetchQifProfiles"
        />
      </div>
    </div>
  </div>
</template>

<script>
  import Swal from 'sweetalert2';
  import axios from 'axios';
  import { computed, onMounted, ref, watch } from 'vue';
  import { __ } from '@/shared/lib/i18n';
  import ImportSourceSelector from './ImportSourceSelector.vue';
  import ImportUploadCard from './ImportUploadCard.vue';
  import ImportDraftTable from './ImportDraftTable.vue';
  import FileImportProfileManager from './FileImportProfileManager.vue';

  const STORAGE_KEY_LAST_PROFILE = 'importLastCsvProfileId';

  export default {
    name: 'ImportPage',
    components: {
      ImportSourceSelector,
      ImportUploadCard,
      ImportDraftTable,
      FileImportProfileManager,
    },
    setup() {
      const sourceType = ref('qif');
      const selectedAccountId = ref('');
      const accounts = ref([]);
      const loadingAccounts = ref(false);

      const profiles = ref([]);
      const loadingProfiles = ref(false);
      const selectedProfileId = ref(null);

      const qifProfiles = ref([]);
      const loadingQifProfiles = ref(false);
      const selectedQifProfileId = ref(null);

      const loading = ref(false);
      const uploadProgress = ref(0);
      const uploadError = ref(null);

      const drafts = ref([]);
      const parseWarnings = ref([]);

      const uploadCard = ref(null);

      const selectedAccountCurrency = ref(null);

      const accountCurrency = computed(() => selectedAccountCurrency.value);

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

      const fetchProfiles = async () => {
        loadingProfiles.value = true;

        try {
          const response = await axios.get('/api/v1/imports/file-profiles', {
            params: { file_type: 'csv' },
          });
          profiles.value = Array.isArray(response.data?.data)
            ? response.data.data
            : [];
        } catch (_error) {
          // Non-critical; profiles will just be empty
        } finally {
          loadingProfiles.value = false;
        }
      };

      const fetchQifProfiles = async () => {
        loadingQifProfiles.value = true;

        try {
          const response = await axios.get('/api/v1/imports/file-profiles', {
            params: { file_type: 'qif' },
          });
          qifProfiles.value = Array.isArray(response.data?.data)
            ? response.data.data
            : [];
        } catch (_error) {
          // Non-critical; QIF profile list will stay empty
        } finally {
          loadingQifProfiles.value = false;
        }
      };

      const autoSelectProfile = (accountId) => {
        const account = accounts.value.find(
          (a) => String(a.id) === String(accountId),
        );

        if (account?.preferred_file_import_profile_id) {
          selectedProfileId.value = account.preferred_file_import_profile_id;
          return;
        }

        const stored = localStorage.getItem(STORAGE_KEY_LAST_PROFILE);
        selectedProfileId.value = stored ? Number(stored) : null;
      };

      const resetParseState = () => {
        drafts.value = [];
        parseWarnings.value = [];
        uploadError.value = null;
        uploadProgress.value = 0;
      };

      const onAccountChange = async (value) => {
        if (drafts.value.length > 0) {
          const result = await Swal.fire({
            text: __(
              'Changing the account will discard all parsed drafts. Continue?',
            ),
            icon: 'warning',
            showCancelButton: true,
            cancelButtonText: __('Cancel'),
            confirmButtonText: __('Continue'),
            buttonsStyling: false,
            customClass: {
              confirmButton: 'btn btn-danger',
              cancelButton: 'btn btn-outline-secondary ms-3',
            },
          });

          if (!result.isConfirmed) {
            return;
          }
        }

        selectedAccountId.value = value;
        selectedAccountCurrency.value = null;
        resetParseState();

        if (value) {
          try {
            const accountResponse = await axios.get(
              `/api/v1/accounts/${value}`,
            );
            selectedAccountCurrency.value =
              accountResponse.data?.config?.currency?.iso_code ?? null;
          } catch {
            // Non-critical, amounts will format without currency symbol
          }
        }

        if (sourceType.value === 'csv') {
          autoSelectProfile(value);
        }
      };

      const onProfileChange = (value) => {
        selectedProfileId.value = value;
      };

      const onQifProfileChange = (value) => {
        selectedQifProfileId.value = value;
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

        const formData = new FormData();
        formData.append('file', file);
        formData.append('source_type', sourceType.value);
        formData.append('account_id', selectedAccountId.value);

        if (sourceType.value === 'csv' && selectedProfileId.value) {
          formData.append('file_import_profile_id', selectedProfileId.value);
        } else if (sourceType.value === 'qif' && selectedQifProfileId.value) {
          formData.append('file_import_profile_id', selectedQifProfileId.value);
        }

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

          if (sourceType.value === 'csv' && selectedProfileId.value) {
            localStorage.setItem(
              STORAGE_KEY_LAST_PROFILE,
              String(selectedProfileId.value),
            );
          }

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

      let isRevertingSourceType = false;

      watch(sourceType, async (newType, oldType) => {
        if (isRevertingSourceType) {
          isRevertingSourceType = false;
          return;
        }

        if (drafts.value.length > 0) {
          const result = await Swal.fire({
            text: __(
              'Changing the source type will discard all parsed drafts. Continue?',
            ),
            icon: 'warning',
            showCancelButton: true,
            cancelButtonText: __('Cancel'),
            confirmButtonText: __('Continue'),
            buttonsStyling: false,
            customClass: {
              confirmButton: 'btn btn-danger',
              cancelButton: 'btn btn-outline-secondary ms-3',
            },
          });

          if (!result.isConfirmed) {
            isRevertingSourceType = true;
            sourceType.value = oldType;
            return;
          }
        }

        resetParseState();
        uploadCard.value?.reset?.();

        if (newType === 'csv') {
          if (!profiles.value.length) {
            fetchProfiles();
          }

          if (selectedAccountId.value) {
            autoSelectProfile(selectedAccountId.value);
          }
        } else if (newType === 'qif') {
          if (!qifProfiles.value.length) {
            fetchQifProfiles();
          }
        }
      });

      const onIgnoreDraft = (draftIndex) => {
        const index = drafts.value.findIndex(
          (d) => d.draft_index === draftIndex,
        );
        if (index !== -1) {
          drafts.value[index] = {
            ...drafts.value[index],
            status: 'ignored',
          };
        }
      };

      const finalizingDraftIndex = ref(null);

      const onFinalizeDraft = (draftIndex) => {
        const draft = drafts.value.find((d) => d.draft_index === draftIndex);
        if (!draft) {
          return;
        }

        finalizingDraftIndex.value = draftIndex;

        const matchedPayeeId = draft.matched_payee?.id ?? null;
        const txType = draft.transaction_type || 'withdrawal';
        const configFromId = draft.config?.account_from_id ?? null;
        const configToId = draft.config?.account_to_id ?? null;

        const transaction = {
          transaction_type: txType,
          date: draft.date,
          schedule: false,
          budget: false,
          reconciled: false,
          comment: null,
          config: {
            account_from_id:
              configFromId === null && txType === 'deposit' && matchedPayeeId
                ? matchedPayeeId
                : configFromId,
            account_to_id:
              configToId === null && txType === 'withdrawal' && matchedPayeeId
                ? matchedPayeeId
                : configToId,
            // For deposits, amount_from is null in normalized data but the form uses
            // amount_from as the primary visible amount field for all transaction types.
            amount_from: draft.config?.amount_from ?? draft.amount ?? null,
            amount_to: draft.config?.amount_to ?? draft.amount ?? null,
          },
        };

        window.dispatchEvent(
          new CustomEvent('initiateCreateFromDraft', {
            detail: {
              type: 'standard',
              transaction,
            },
          }),
        );
      };

      const onTransactionCreated = () => {
        if (finalizingDraftIndex.value === null) {
          return;
        }

        const index = drafts.value.findIndex(
          (d) => d.draft_index === finalizingDraftIndex.value,
        );
        if (index !== -1) {
          drafts.value[index] = {
            ...drafts.value[index],
            status: 'finalized',
          };
        }

        finalizingDraftIndex.value = null;
      };

      onMounted(() => {
        fetchAccounts();
        fetchQifProfiles();
        window.addEventListener('transaction-created', onTransactionCreated);
      });

      return {
        sourceType,
        selectedAccountId,
        accounts,
        loadingAccounts,
        profiles,
        loadingProfiles,
        selectedProfileId,
        qifProfiles,
        loadingQifProfiles,
        selectedQifProfileId,
        loading,
        uploadProgress,
        uploadError,
        drafts,
        parseWarnings,
        uploadCard,
        accountCurrency,
        selectedAccountCurrency,
        fetchProfiles,
        fetchQifProfiles,
        onAccountChange,
        onProfileChange,
        onQifProfileChange,
        onSubmit,
        onIgnoreDraft,
        onFinalizeDraft,
      };
    },
  };
</script>
