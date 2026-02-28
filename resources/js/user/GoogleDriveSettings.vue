<template>
  <div class="card" id="googleDriveConfigForm">
    <form
      accept-charset="UTF-8"
      @submit.prevent="onSubmit"
      @keydown="form.onKeydown($event)"
      autocomplete="off"
    >
      <div class="card-header d-flex justify-content-between">
        <div class="card-title">
          {{ __('Google Drive Configuration') }}
        </div>
        <div>
          <span
            class="fa fa-info-circle text-info"
            :title="
              __(
                'Configure a Google Drive service account to import documents. Credentials are encrypted and stored securely.',
              )
            "
            data-coreui-toggle="tooltip"
            data-coreui-placement="top"
          ></span>
        </div>
      </div>
      <div class="card-body" v-if="!sandbox_mode">
        <div v-if="!googleDriveEnabled" class="alert alert-warning mb-0">
          {{
            __(
              'Google Drive import is disabled by system configuration. Contact your administrator to enable this feature.',
            )
          }}
        </div>

        <div v-else-if="!hasConfig && !showForm" class="text-center py-2">
          <p class="mb-3">{{ __('No Google Drive configuration yet.') }}</p>
          <button
            type="button"
            class="btn btn-primary"
            dusk="button-add-google-drive"
            @click="showForm = true"
          >
            <i class="fa fa-plus"></i>
            {{ __('Add Google Drive') }}
          </button>
        </div>

        <div v-if="hasConfig || showForm">
          <div class="row mb-3" v-if="hasConfig">
            <label class="col-form-label col-sm-3">
              {{ __('Service Account Email') }}
            </label>
            <div class="col-sm-9">
              <p class="form-control-plaintext" dusk="service-account-email">
                {{ serviceAccountEmail || __('Not available') }}
              </p>
            </div>
          </div>

          <div class="row mb-3">
            <label for="service_account_json" class="col-form-label col-sm-3">
              {{ __('Service Account JSON') }}
            </label>
            <div class="col-sm-9">
              <div class="position-relative">
                <textarea
                  class="form-control"
                  :class="{ 'password-masked': !showServiceAccountJson }"
                  id="service_account_json"
                  name="service_account_json"
                  v-model="form.service_account_json"
                  :placeholder="
                    hasConfig
                      ? __('Leave blank to keep existing credentials')
                      : __('Paste your Google Cloud Service Account JSON key')
                  "
                  @input="resetTestResult"
                  rows="6"
                  :style="{
                    paddingRight: '80px',
                    fontFamily: 'monospace',
                    fontSize: '0.875rem',
                  }"
                ></textarea>
                <div
                  class="position-absolute"
                  style="top: 8px; right: 8px; display: flex; gap: 4px"
                >
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    @click="toggleServiceAccountVisibility"
                    :aria-label="
                      showServiceAccountJson
                        ? __('Hide service account JSON')
                        : __('Show service account JSON')
                    "
                    data-coreui-toggle="tooltip"
                    data-coreui-placement="top"
                    :title="
                      showServiceAccountJson
                        ? __('Hide credentials')
                        : __('Show credentials')
                    "
                  >
                    <i
                      :class="[
                        'fa',
                        showServiceAccountJson ? 'fa-eye-slash' : 'fa-eye',
                      ]"
                    ></i>
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-info"
                    data-coreui-toggle="tooltip"
                    data-coreui-placement="top"
                    :title="
                      __(
                        'Paste the full JSON key for the service account. This will be encrypted before storage.',
                      )
                    "
                  >
                    <i class="fa fa-info-circle"></i>
                  </button>
                </div>
              </div>
              <HasError field="service_account_json" :form="form" />
              <small
                class="form-text text-muted"
                v-if="hasConfig"
                dusk="service-account-json-hint"
              >
                {{
                  __(
                    'Current credentials are hidden. Enter new JSON only if you want to change it.',
                  )
                }}
              </small>
            </div>
          </div>

          <div class="row mb-3">
            <label for="folder_id" class="col-form-label col-sm-3">
              {{ __('Folder ID') }}
            </label>
            <div class="col-sm-9">
              <div class="input-group">
                <input
                  type="text"
                  class="form-control"
                  id="folder_id"
                  name="folder_id"
                  v-model="form.folder_id"
                  :placeholder="
                    __(
                      'Enter Google Drive folder ID. You can also paste the full folder URL and it will be extracted automatically.',
                    )
                  "
                  @blur="normalizeFolderId"
                  @input="resetTestResult"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Example: https://drive.google.com/drive/folders/{FOLDER_ID} - copy the part after /folders/',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="folder_id" :form="form" />
              <small class="form-text text-muted">
                {{
                  __(
                    'You must share the folder with the service account email for access.',
                  )
                }}
              </small>
            </div>
          </div>

          <div class="row mb-3">
            <label class="col-form-label col-sm-3">
              {{ __('Delete after import') }}
            </label>
            <div class="col-sm-9">
              <div class="form-check form-switch">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="delete_after_import"
                  v-model="form.delete_after_import"
                  @change="resetTestResult"
                />
                <label class="form-check-label" for="delete_after_import">
                  {{ __('Delete files after successful import') }}
                </label>
              </div>
              <small class="form-text text-muted">
                {{
                  __(
                    'Requires delete permission for the shared folder. Test connection to verify.',
                  )
                }}
              </small>
              <div v-if="showDeleteWarning" class="text-warning mt-1">
                <i class="fa fa-exclamation-triangle"></i>
                {{
                  __(
                    'Delete permission not detected. Disable delete-after-import or update folder permissions.',
                  )
                }}
              </div>
            </div>
          </div>

          <div class="row mb-3">
            <label class="col-form-label col-sm-3">
              {{ __('Enabled') }}
            </label>
            <div class="col-sm-9">
              <div class="form-check form-switch">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="enabled"
                  v-model="form.enabled"
                />
                <label class="form-check-label" for="enabled">
                  {{ __('Enable Google Drive monitoring') }}
                </label>
              </div>
            </div>
          </div>

          <div v-if="hasConfig" class="row mb-3">
            <label class="col-form-label col-sm-3">
              {{ __('Last sync') }}
            </label>
            <div class="col-sm-9">
              <p class="form-control-plaintext" dusk="last-sync-at">
                {{ formattedLastSyncAt }}
              </p>
            </div>
          </div>

          <div v-if="hasConfig && lastError" class="row mb-3">
            <label class="col-form-label col-sm-3">
              {{ __('Last error') }}
            </label>
            <div class="col-sm-9">
              <div class="alert alert-warning mb-0" role="alert">
                <i class="fa fa-exclamation-triangle"></i>
                {{ lastError }}
              </div>
            </div>
          </div>

          <div v-if="testResult" class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
              <div
                :class="[
                  'alert',
                  testResult.success ? 'alert-success' : 'alert-danger',
                ]"
                role="alert"
              >
                <i
                  :class="[
                    'fa',
                    testResult.success ? 'fa-check-circle' : 'fa-times-circle',
                  ]"
                ></i>
                {{ testResult.message }}
                <div v-if="testResult.success" class="mt-2">
                  <div>
                    {{
                      __('Files found: :count', {
                        count: testResult.file_count,
                      })
                    }}
                  </div>
                  <div>
                    {{
                      testResult.has_delete_permission
                        ? __('Delete permission: available')
                        : __('Delete permission: not available')
                    }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="card-body" v-else>
        <div class="alert alert-warning">
          {{
            __(
              'You are in sandbox mode. You cannot change the Google Drive settings.',
            )
          }}
        </div>
      </div>

      <div class="card-footer" v-if="!sandbox_mode && (hasConfig || showForm)">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <Button
              class="btn btn-primary me-2"
              :form="form"
              dusk="button-save-google-drive"
            >
              <i class="fa fa-save me-1" v-show="!form.busy"></i>
              {{ hasConfig ? __('Update') : __('Save') }}
            </Button>

            <button
              type="button"
              class="btn btn-secondary me-2"
              @click="testConnection"
              :disabled="!canTest || testingConnection"
              dusk="button-test-google-drive"
            >
              <i
                :class="[
                  'fa me-1',
                  testingConnection ? 'fa-spinner fa-spin' : 'fa-plug',
                ]"
              ></i>
              {{ __('Test Connection') }}
            </button>

            <button
              v-if="hasConfig"
              type="button"
              class="btn btn-outline-primary me-2"
              @click="triggerSync"
              :disabled="syncing"
              dusk="button-sync-google-drive"
            >
              <i
                :class="['fa', syncing ? 'fa-spinner fa-spin' : 'fa-sync']"
              ></i>
              {{ __('Manual Sync') }}
            </button>

            <button
              v-if="!hasConfig && showForm"
              type="button"
              class="btn btn-outline-secondary"
              @click="cancelAdd"
              dusk="button-cancel-add-google-drive"
            >
              <i class="fa fa-times me-1"></i>
              {{ __('Cancel') }}
            </button>
          </div>

          <button
            v-if="hasConfig"
            type="button"
            class="btn btn-danger"
            @click="deleteConfig"
            dusk="button-delete-google-drive"
          >
            <i class="fa fa-trash"></i>
            {{ __('Delete Configuration') }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<script>
  import { __ } from '@/i18n';
  import { initializeBootstrapTooltips } from '@/helpers';
  import * as toastHelpers from '@/toast';
  import Form from 'vform';
  import { Button, HasError } from 'vform/src/components/bootstrap5';
  import Swal from 'sweetalert2';

  export default {
    name: 'GoogleDriveSettings',
    components: {
      Button,
      HasError,
    },
    props: {
      route: {
        type: Function,
        default: () => window.route,
      },
    },
    data: () => ({
      form: new Form({
        service_account_json: '',
        folder_id: '',
        delete_after_import: false,
        enabled: true,
      }),
      configId: null,
      hasConfig: false,
      showForm: false,
      serviceAccountEmail: null,
      lastSyncAt: null,
      lastError: null,
      testResult: null,
      testingConnection: false,
      syncing: false,
      showServiceAccountJson: false,
      sandbox_mode: window.YAFFA.config.sandbox_mode,
    }),
    computed: {
      googleDriveEnabled() {
        return (
          window.YAFFA?.config?.ai_documents?.google_drive?.enabled ?? false
        );
      },
      canTest() {
        return (
          this.googleDriveEnabled &&
          this.form.folder_id &&
          (this.form.service_account_json || this.hasConfig)
        );
      },
      formattedLastSyncAt() {
        if (!this.lastSyncAt) {
          return __('Never');
        }
        try {
          const date = new Date(this.lastSyncAt);
          if (Number.isNaN(date.getTime())) {
            return this.lastSyncAt;
          }
          return date.toLocaleString();
        } catch (e) {
          return this.lastSyncAt;
        }
      },
      showDeleteWarning() {
        return (
          this.form.delete_after_import &&
          this.testResult &&
          this.testResult.success &&
          !this.testResult.has_delete_permission
        );
      },
    },
    mounted() {
      if (!this.googleDriveEnabled) {
        return;
      }

      this.loadConfig();

      // Initialize tooltips
      initializeBootstrapTooltips(this.$el);
    },
    updated() {
      // Re-initialize tooltips after DOM updates (e.g., when form is shown)
      this.$nextTick(() => {
        initializeBootstrapTooltips(this.$el);
      });
    },
    methods: {
      loadConfig() {
        axios
          .get(this.route('api.v1.google-drive.config.show'))
          .then((response) => {
            if (response.data && response.data.id) {
              this.configId = response.data.id;
              this.serviceAccountEmail = response.data.service_account_email;
              this.form.folder_id = response.data.folder_id;
              this.form.delete_after_import =
                response.data.delete_after_import || false;
              this.form.enabled = response.data.enabled ?? true;
              this.lastSyncAt = response.data.last_sync_at;
              this.lastError = response.data.last_error;
              this.hasConfig = true;
              this.showForm = true;
            }
          })
          .catch((error) => {
            if (error.response && error.response.status === 404) {
              this.hasConfig = false;
              this.showForm = false;
            } else {
              console.error('Failed to load Google Drive config:', error);
            }
          });
      },
      normalizeFolderId() {
        if (!this.form.folder_id) {
          return;
        }

        const raw = this.form.folder_id.trim();
        const folderId = this.extractFolderId(raw);
        if (folderId) {
          this.form.folder_id = folderId;
        }
      },
      extractFolderId(value) {
        if (!value) {
          return null;
        }

        if (!value.includes('drive.google.com')) {
          return value;
        }

        const folderMatch = value.match(/\/folders\/([^/?#]+)/i);
        if (folderMatch && folderMatch[1]) {
          return folderMatch[1];
        }

        const openMatch = value.match(/[?&]id=([^&#]+)/i);
        if (openMatch && openMatch[1]) {
          return openMatch[1];
        }

        return value;
      },
      resetTestResult() {
        this.testResult = null;
      },
      toggleServiceAccountVisibility() {
        this.showServiceAccountJson = !this.showServiceAccountJson;
      },
      onSubmit() {
        if (!this.googleDriveEnabled) {
          toastHelpers.showErrorToast(
            __('Google Drive import is disabled by system configuration.'),
          );
          return;
        }

        let _vue = this;
        this.form.busy = true;
        this.testResult = null;

        this.normalizeFolderId();

        const url = this.hasConfig
          ? this.route('api.v1.google-drive.config.update', {
              id: this.configId,
            })
          : this.route('api.v1.google-drive.config.store');
        const method = this.hasConfig ? 'patch' : 'post';

        const formData = { ...this.form.data() };
        if (this.hasConfig && !formData.service_account_json) {
          delete formData.service_account_json;
        }

        this.form[method](url, formData)
          .then((response) => {
            if (response.status === 200 || response.status === 201) {
              toastHelpers.showSuccessToast(
                this.hasConfig
                  ? __('Google Drive configuration updated')
                  : __('Google Drive configuration created'),
              );

              this.configId = response.data.id;
              this.hasConfig = true;
              this.showForm = true;
              this.serviceAccountEmail = response.data.service_account_email;
              this.form.service_account_json = '';
            }
          })
          .catch((error) => {
            if (error.response && error.response.status === 422) {
              toastHelpers.showErrorToast(
                __('Validation failed. Please check the form for errors.'),
              );
            } else {
              console.error(error);
              toastHelpers.showErrorToast(
                __('An error occurred. Please try again later.'),
              );
            }
          })
          .finally(() => {
            _vue.form.busy = false;
          });
      },
      testConnection() {
        if (!this.googleDriveEnabled) {
          toastHelpers.showErrorToast(
            __('Google Drive import is disabled by system configuration.'),
          );
          return;
        }

        this.testingConnection = true;
        this.testResult = null;

        this.normalizeFolderId();

        const testData = {
          service_account_json:
            this.form.service_account_json || '__existing__',
          folder_id: this.form.folder_id,
        };

        axios
          .post(this.route('api.v1.google-drive.config.test'), testData)
          .then((response) => {
            this.testResult = {
              success: true,
              message: response.data.message || __('Connection successful'),
              file_count: response.data.file_count ?? 0,
              has_delete_permission:
                response.data.has_delete_permission ?? false,
            };
          })
          .catch((error) => {
            this.testResult = {
              success: false,
              message:
                error.response?.data?.error?.message ||
                error.response?.data?.message ||
                __('Connection test failed'),
            };
          })
          .finally(() => {
            this.testingConnection = false;
          });
      },
      triggerSync() {
        if (!this.googleDriveEnabled) {
          toastHelpers.showErrorToast(
            __('Google Drive import is disabled by system configuration.'),
          );
          return;
        }

        if (!this.configId) {
          return;
        }

        this.syncing = true;
        axios
          .post(
            this.route('api.v1.google-drive.config.sync', {
              id: this.configId,
            }),
          )
          .then((response) => {
            toastHelpers.showInfoToast(
              response.data?.message || __('Sync queued'),
            );
          })
          .catch((error) => {
            console.error(error);
            toastHelpers.showErrorToast(
              __('Failed to trigger sync. Please try again.'),
            );
          })
          .finally(() => {
            this.syncing = false;
          });
      },
      deleteConfig() {
        if (!this.googleDriveEnabled) {
          toastHelpers.showErrorToast(
            __('Google Drive import is disabled by system configuration.'),
          );
          return;
        }

        Swal.fire({
          animation: false,
          text: this.__('Are you sure you want to delete this configuration?'),
          icon: 'warning',
          showCancelButton: true,
          cancelButtonText: this.__('Cancel'),
          confirmButtonText: this.__('Confirm'),
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary ms-3',
          },
        }).then((result) => {
          if (result.isConfirmed) {
            axios
              .delete(
                this.route('api.v1.google-drive.config.destroy', {
                  id: this.configId,
                }),
              )
              .then(() => {
                this.configId = null;
                this.hasConfig = false;
                this.showForm = false;
                this.form.reset();
                this.testResult = null;
                this.serviceAccountEmail = null;
                this.lastSyncAt = null;
                this.lastError = null;

                toastHelpers.showSuccessToast(
                  __('Google Drive configuration deleted'),
                );
              })
              .catch((error) => {
                console.error(error);
                toastHelpers.showErrorToast(
                  __('Failed to delete configuration. Please try again.'),
                );
              });
          }
        });
      },
      cancelAdd() {
        this.showForm = false;
        this.form.reset();
        this.testResult = null;
      },
      __,
    },
  };
</script>

<style scoped>
  .password-masked {
    -webkit-text-security: disc;
    -moz-text-security: disc;
    text-security: disc;
  }
</style>
