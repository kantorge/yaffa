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
    data() {
      return {
        sourceType: 'qif',
        selectedAccountId: '',
        accounts: [],
        loadingAccounts: false,
        profiles: [],
        loadingProfiles: false,
        selectedProfileId: null,
        qifProfiles: [],
        loadingQifProfiles: false,
        selectedQifProfileId: null,
        loading: false,
        uploadProgress: 0,
        uploadError: null,
        drafts: [],
        parseWarnings: [],
        selectedAccountCurrency: null,
        finalizingDraftIndex: null,
        isRevertingSourceType: false,
      };
    },
    computed: {
      accountCurrency() {
        return this.selectedAccountCurrency;
      },
    },
    watch: {
      async sourceType(newType, oldType) {
        if (this.isRevertingSourceType) {
          this.isRevertingSourceType = false;
          return;
        }

        if (this.drafts.length > 0) {
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
            this.isRevertingSourceType = true;
            this.sourceType = oldType;
            return;
          }
        }

        this.resetParseState();
        this.$refs.uploadCard?.reset?.();

        if (newType === 'csv') {
          this.selectedQifProfileId = null;
          if (!this.profiles.length) {
            await this.fetchProfiles();
          }
          if (this.selectedAccountId) {
            this.autoSelectProfile(this.selectedAccountId);
          }
        } else if (newType === 'qif') {
          this.selectedProfileId = null;
          if (!this.qifProfiles.length) {
            await this.fetchQifProfiles();
          }
          if (this.selectedAccountId) {
            this.autoSelectProfile(this.selectedAccountId);
          }
        }
      },
    },
    mounted() {
      this.fetchAccounts();
      this.fetchQifProfiles();
      window.addEventListener('transaction-created', this.onTransactionCreated);
    },
    unmounted() {
      window.removeEventListener(
        'transaction-created',
        this.onTransactionCreated,
      );
    },
    methods: {
      async fetchAccounts() {
        this.loadingAccounts = true;
        try {
          const response = await axios.get('/api/v1/accounts', {
            params: { limit: 0 },
          });
          this.accounts = Array.isArray(response.data) ? response.data : [];
        } catch (_error) {
          this.uploadError = __(
            'Unable to load accounts. Please refresh the page and try again.',
          );
        } finally {
          this.loadingAccounts = false;
        }
      },
      async fetchProfiles() {
        this.loadingProfiles = true;
        try {
          const response = await axios.get('/api/v1/imports/file-profiles', {
            params: { file_type: 'csv' },
          });
          this.profiles = Array.isArray(response.data?.data)
            ? response.data.data
            : [];
        } catch (_error) {
          // Non-critical; profiles will just be empty
        } finally {
          this.loadingProfiles = false;
        }
      },
      async fetchQifProfiles() {
        this.loadingQifProfiles = true;
        try {
          const response = await axios.get('/api/v1/imports/file-profiles', {
            params: { file_type: 'qif' },
          });
          this.qifProfiles = Array.isArray(response.data?.data)
            ? response.data.data
            : [];
        } catch (_error) {
          // Non-critical; QIF profile list will stay empty
        } finally {
          this.loadingQifProfiles = false;
        }
      },
      autoSelectProfile(accountId) {
        const account = this.accounts.find(
          (a) => String(a.id) === String(accountId),
        );
        const preferredId = account?.preferred_file_import_profile_id ?? null;

        if (this.sourceType === 'csv') {
          const inCsvProfiles = preferredId
            ? this.profiles.some((p) => p.id === preferredId)
            : false;

          if (inCsvProfiles) {
            this.selectedProfileId = preferredId;
          } else {
            const stored = localStorage.getItem(STORAGE_KEY_LAST_PROFILE);
            this.selectedProfileId = stored ? Number(stored) : null;
          }
        } else if (this.sourceType === 'qif') {
          const inQifProfiles = preferredId
            ? this.qifProfiles.some((p) => p.id === preferredId)
            : false;

          this.selectedQifProfileId = inQifProfiles ? preferredId : null;
        }
      },
      resetParseState() {
        this.drafts = [];
        this.parseWarnings = [];
        this.uploadError = null;
        this.uploadProgress = 0;
      },
      async onAccountChange(value) {
        if (this.drafts.length > 0) {
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

        this.selectedAccountId = value;
        this.selectedAccountCurrency = null;
        this.resetParseState();

        if (value) {
          try {
            const accountResponse = await axios.get(`/api/v1/accounts/${value}`);
            this.selectedAccountCurrency =
              accountResponse.data?.config?.currency?.iso_code ?? null;
          } catch {
            // Non-critical, amounts will format without currency symbol
          }
        }

        if (value) {
          this.autoSelectProfile(value);
        }
      },
      onProfileChange(value) {
        this.selectedProfileId = value;
      },
      onQifProfileChange(value) {
        this.selectedQifProfileId = value;
      },
      async onSubmit({ file }) {
        this.uploadError = null;

        if (!file) {
          this.uploadError = __('Please select a file first.');
          return;
        }
        if (!this.selectedAccountId) {
          this.uploadError = __('Please select a target account first.');
          return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('source_type', this.sourceType);
        formData.append('account_id', this.selectedAccountId);

        if (this.sourceType === 'csv' && this.selectedProfileId) {
          formData.append('file_import_profile_id', this.selectedProfileId);
        } else if (this.sourceType === 'qif' && this.selectedQifProfileId) {
          formData.append(
            'file_import_profile_id',
            this.selectedQifProfileId,
          );
        }

        this.loading = true;
        this.uploadProgress = 0;

        try {
          const response = await axios.post('/api/v1/imports/parse', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (event) => {
              if (!event.total || event.total <= 0) {
                return;
              }
              const progress = Math.round((event.loaded * 100) / event.total);
              this.uploadProgress = Math.min(100, Math.max(0, progress));
            },
          });

          this.drafts = Array.isArray(response.data?.drafts)
            ? response.data.drafts
            : [];
          this.parseWarnings = Array.isArray(response.data?.warnings)
            ? response.data.warnings
            : [];

          if (this.sourceType === 'csv' && this.selectedProfileId) {
            localStorage.setItem(
              STORAGE_KEY_LAST_PROFILE,
              String(this.selectedProfileId),
            );
          }

          this.$refs.uploadCard?.reset?.();
        } catch (error) {
          if (error?.response?.data?.errors) {
            const firstKey = Object.keys(error.response.data.errors)[0];
            if (
              firstKey &&
              Array.isArray(error.response.data.errors[firstKey])
            ) {
              this.uploadError = error.response.data.errors[firstKey][0];
            } else {
              this.uploadError = __(
                'Upload failed. Please review your input and try again.',
              );
            }
          } else if (error?.response?.data?.error?.message) {
            this.uploadError = error.response.data.error.message;
          } else if (error?.response?.data?.message) {
            this.uploadError = error.response.data.message;
          } else {
            this.uploadError = __(
              'Upload failed due to a network or server error.',
            );
          }
        } finally {
          this.loading = false;
        }
      },
      onIgnoreDraft(draftIndex) {
        const index = this.drafts.findIndex(
          (d) => d.draft_index === draftIndex,
        );
        if (index !== -1) {
          this.drafts[index] = { ...this.drafts[index], status: 'ignored' };
        }
      },
      onFinalizeDraft(draftIndex) {
        const draft = this.drafts.find((d) => d.draft_index === draftIndex);
        if (!draft) {
          return;
        }

        this.finalizingDraftIndex = draftIndex;

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
            detail: { type: 'standard', transaction },
          }),
        );
      },
      onTransactionCreated() {
        if (this.finalizingDraftIndex === null) {
          return;
        }
        const index = this.drafts.findIndex(
          (d) => d.draft_index === this.finalizingDraftIndex,
        );
        if (index !== -1) {
          this.drafts[index] = {
            ...this.drafts[index],
            status: 'finalized',
          };
        }
        this.finalizingDraftIndex = null;
      },
      __,
    },
  };
</script>
