<template>
  <div id="googleDriveConfigForm" class="card">
    <form
      accept-charset="UTF-8"
      autocomplete="off"
      @submit.prevent="onSubmit"
      @keydown="form.onKeydown($event)"
    >
      <div class="card-header d-flex justify-content-between">
        <div class="card-title">
          {{ __('user.googleDriveSettings.cardTitle') }}
        </div>
        <div>
          <span
            v-if="!aiProcessingEnabled"
            class="fa fa-exclamation-triangle text-warning me-2"
            :title="__('user.googleDriveSettings.aiProcessingDisabledWarning')"
            data-coreui-toggle="tooltip"
            data-coreui-placement="top"
          ></span>
          <span
            class="fa fa-info-circle text-info"
            :title="__('user.googleDriveSettings.cardInfoTooltip')"
            data-coreui-toggle="tooltip"
            data-coreui-placement="top"
          ></span>
        </div>
      </div>
      <div v-if="!sandbox_mode" class="card-body">
        <div v-if="!hasConfig && !showForm" class="text-center py-2">
          <p class="mb-3">
            {{ __('user.googleDriveSettings.emptyState.noConfig') }}
          </p>
          <button
            type="button"
            class="btn btn-primary"
            dusk="button-add-google-drive"
            @click="showForm = true"
          >
            <i class="fa fa-plus"></i>
            {{ __('user.googleDriveSettings.emptyState.addButton') }}
          </button>
        </div>

        <div v-if="hasConfig || showForm">
          <div v-if="hasConfig" class="row mb-3">
            <label class="col-form-label col-sm-3">
              {{
                __('user.googleDriveSettings.fields.serviceAccountEmail.label')
              }}
            </label>
            <div class="col-sm-9">
              <p class="form-control-plaintext" dusk="service-account-email">
                {{
                  serviceAccountEmail ||
                  __('user.googleDriveSettings.common.notAvailable')
                }}
              </p>
            </div>
          </div>

          <!-- JSON key file upload shortcut -->
          <div class="row mb-3">
            <label for="json_key_file" class="col-form-label col-sm-3">
              {{ __('user.googleDriveSettings.fields.jsonKeyFile.label') }}
            </label>
            <div class="col-sm-9">
              <div class="input-group">
                <input
                  id="json_key_file"
                  ref="jsonFileInput"
                  type="file"
                  class="form-control"
                  accept=".json"
                  dusk="json-key-file-input"
                  @change="onJsonFileSelect"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __('user.googleDriveSettings.fields.jsonKeyFile.uploadInfo')
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <div
                v-if="jsonFileError"
                class="text-danger mt-1"
                dusk="json-file-error"
              >
                <small>{{ jsonFileError }}</small>
              </div>
            </div>
          </div>

          <div class="row mb-3">
            <label for="service_account_json" class="col-form-label col-sm-3">
              {{
                __('user.googleDriveSettings.fields.serviceAccountJson.label')
              }}
            </label>
            <div class="col-sm-9">
              <div class="position-relative">
                <textarea
                  id="service_account_json"
                  v-model="form.service_account_json"
                  class="form-control"
                  :class="{ 'password-masked': !showServiceAccountJson }"
                  name="service_account_json"
                  :placeholder="
                    hasConfig
                      ? __(
                          'user.googleDriveSettings.fields.serviceAccountJson.keepExistingPlaceholder',
                        )
                      : __(
                          'user.googleDriveSettings.fields.serviceAccountJson.pastePlaceholder',
                        )
                  "
                  rows="6"
                  :style="{
                    paddingRight: '80px',
                    fontFamily: 'monospace',
                    fontSize: '0.875rem',
                  }"
                  @input="resetTestResult"
                ></textarea>
                <div
                  class="position-absolute"
                  style="top: 8px; right: 8px; display: flex; gap: 4px"
                >
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    :aria-label="
                      showServiceAccountJson
                        ? __(
                            'user.googleDriveSettings.fields.serviceAccountJson.hideAria',
                          )
                        : __(
                            'user.googleDriveSettings.fields.serviceAccountJson.showAria',
                          )
                    "
                    data-coreui-toggle="tooltip"
                    data-coreui-placement="top"
                    :title="
                      showServiceAccountJson
                        ? __(
                            'user.googleDriveSettings.fields.serviceAccountJson.hideTitle',
                          )
                        : __(
                            'user.googleDriveSettings.fields.serviceAccountJson.showTitle',
                          )
                    "
                    @click="toggleServiceAccountVisibility"
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
                        'user.googleDriveSettings.fields.serviceAccountJson.infoTooltip',
                      )
                    "
                  >
                    <i class="fa fa-info-circle"></i>
                  </button>
                </div>
              </div>
              <HasError field="service_account_json" :form="form" />
            </div>
          </div>

          <!-- Folder ID -->
          <div class="row mb-3">
            <label for="folder_id" class="col-form-label col-sm-3">
              {{ __('user.googleDriveSettings.fields.folderId.label') }}
            </label>
            <div class="col-sm-9">
              <div class="input-group">
                <input
                  id="folder_id"
                  v-model="form.folder_id"
                  type="text"
                  class="form-control"
                  name="folder_id"
                  :placeholder="
                    __('user.googleDriveSettings.fields.folderId.placeholder')
                  "
                  @blur="normalizeFolderId"
                  @input="resetTestResult"
                />
                <button
                  type="button"
                  class="btn btn-outline-secondary"
                  :disabled="!canBrowseFolders"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    canBrowseFolders
                      ? __(
                          'user.googleDriveSettings.fields.folderId.browseEnabledTitle',
                        )
                      : __(
                          'user.googleDriveSettings.fields.folderId.browseDisabledTitle',
                        )
                  "
                  dusk="button-browse-folder"
                  @click="openFolderBrowser('import')"
                >
                  <i class="fa fa-folder-open"></i>
                </button>
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'user.googleDriveSettings.fields.folderId.exampleTooltip',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="folder_id" :form="form" />
              <small class="form-text text-muted">
                {{ __('user.googleDriveSettings.fields.folderId.helpText') }}
              </small>
            </div>
          </div>

          <!-- Folder display name -->
          <div class="row mb-3">
            <label for="folder_name" class="col-form-label col-sm-3">
              {{ __('user.googleDriveSettings.fields.folderName.label') }}
            </label>
            <div class="col-sm-9">
              <div class="input-group">
                <input
                  id="folder_name"
                  v-model="form.folder_name"
                  type="text"
                  class="form-control"
                  name="folder_name"
                  maxlength="255"
                  :placeholder="
                    __('user.googleDriveSettings.fields.folderName.placeholder')
                  "
                  dusk="folder-name"
                />
                <button
                  type="button"
                  class="btn btn-outline-secondary"
                  :disabled="
                    !canBrowseFolders || !form.folder_id || fetchingFolderName
                  "
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'user.googleDriveSettings.fields.folderName.refetchTitle',
                    )
                  "
                  dusk="button-fetch-folder-name"
                  @click="fetchFolderName('import')"
                >
                  <i
                    :class="[
                      'fa',
                      fetchingFolderName ? 'fa-spinner fa-spin' : 'fa-refresh',
                    ]"
                  ></i>
                </button>
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __('user.googleDriveSettings.fields.folderName.infoTooltip')
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="folder_name" :form="form" />
            </div>
          </div>

          <!-- Post-import file disposition -->
          <div class="row mb-3">
            <label class="col-form-label col-sm-3">
              {{ __('user.googleDriveSettings.sections.afterImport.label') }}
            </label>
            <div class="col-sm-9">
              <div class="d-flex align-items-center mb-3">
                <small class="form-text mb-0">
                  {{
                    __(
                      'user.googleDriveSettings.sections.afterImport.actionOrderHint',
                    )
                  }}
                </small>
                <span
                  class="ms-2 text-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'user.googleDriveSettings.sections.afterImport.permissionTipTooltip',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>

              <!-- Estimated capabilities banner -->
              <div
                v-if="capabilitiesSource === 'estimated'"
                class="alert alert-warning py-2 mb-3"
                dusk="capabilities-estimated-banner"
              >
                <i class="fa fa-exclamation-triangle me-1"></i>
                {{
                  __(
                    'user.googleDriveSettings.sections.afterImport.estimatedBanner',
                  )
                }}
              </div>

              <!-- Test file deleted notice -->
              <div
                v-if="testNotice"
                class="alert alert-info py-2 mb-3"
                dusk="test-notice"
              >
                <i class="fa fa-info-circle me-1"></i>
                {{ testNotice }}
              </div>

              <!-- Action checkboxes -->
              <div
                v-for="action in dispositionActions"
                :key="action.key"
                class="mb-2"
              >
                <div class="d-flex align-items-start">
                  <div class="form-check me-2">
                    <input
                      :id="'action_' + action.key"
                      v-model="form.post_import_actions"
                      class="form-check-input"
                      type="checkbox"
                      :value="action.key"
                      @change="resetTestResult"
                    />
                    <label
                      class="form-check-label"
                      :for="'action_' + action.key"
                    >
                      {{ action.label }}
                    </label>
                  </div>
                  <!-- Capability badge -->
                  <span
                    v-if="getCapability(action.key) === true"
                    class="badge bg-success ms-1 align-self-center"
                    dusk="capability-verified"
                  >
                    <i class="fa fa-check me-1"></i
                    >{{ __('user.googleDriveSettings.badges.verified') }}
                  </span>
                  <span
                    v-else-if="getCapability(action.key) === false"
                    class="badge bg-warning text-dark ms-1 align-self-center"
                    data-coreui-toggle="tooltip"
                    data-coreui-placement="top"
                    :title="
                      __('user.googleDriveSettings.badges.mayFailTooltip')
                    "
                    dusk="capability-may-fail"
                  >
                    <i class="fa fa-exclamation-triangle me-1"></i
                    >{{ __('user.googleDriveSettings.badges.mayFail') }}
                  </span>
                  <span
                    v-else-if="
                      testCapabilities !== null &&
                      getCapability(action.key) === null
                    "
                    class="badge bg-secondary ms-1 align-self-center"
                    data-coreui-toggle="tooltip"
                    data-coreui-placement="top"
                    :title="getCapabilityNullReason(action.key)"
                    dusk="capability-not-tested"
                  >
                    {{ __('user.googleDriveSettings.badges.notTested') }}
                  </span>
                </div>
              </div>

              <!-- Processed folder sub-form -->
              <div
                v-if="moveToProcessedSelected"
                class="mt-3 p-3 border rounded"
                dusk="processed-folder-section"
              >
                <!-- Processed folder ID -->
                <div class="mb-2">
                  <label for="processed_folder_id" class="form-label">
                    {{
                      __(
                        'user.googleDriveSettings.fields.processedFolderId.label',
                      )
                    }}
                    <span class="text-danger">*</span>
                  </label>
                  <div class="input-group">
                    <input
                      id="processed_folder_id"
                      v-model="form.processed_folder_id"
                      type="text"
                      class="form-control"
                      :class="{ 'is-invalid': processedFolderIdError }"
                      :placeholder="
                        __(
                          'user.googleDriveSettings.fields.processedFolderId.placeholder',
                        )
                      "
                      dusk="processed-folder-id"
                      @blur="normalizeProcessedFolderId"
                      @input="resetTestResult"
                    />
                    <button
                      type="button"
                      class="btn btn-outline-secondary"
                      :disabled="!canBrowseFolders"
                      data-coreui-toggle="tooltip"
                      data-coreui-placement="top"
                      :title="
                        canBrowseFolders
                          ? __(
                              'user.googleDriveSettings.fields.folderId.browseEnabledTitle',
                            )
                          : __(
                              'user.googleDriveSettings.fields.folderId.browseDisabledTitle',
                            )
                      "
                      dusk="button-browse-processed-folder"
                      @click="openFolderBrowser('processed')"
                    >
                      <i class="fa fa-folder-open"></i>
                    </button>
                    <span
                      class="input-group-text btn btn-outline-input-info"
                      data-coreui-toggle="tooltip"
                      data-coreui-placement="top"
                      :title="
                        __(
                          'user.googleDriveSettings.fields.folderId.exampleTooltip',
                        )
                      "
                    >
                      <i class="fa fa-info-circle"></i>
                    </span>
                    <div
                      v-if="processedFolderIdError"
                      class="invalid-feedback"
                      dusk="processed-folder-id-error"
                    >
                      {{ processedFolderIdError }}
                    </div>
                  </div>
                  <HasError field="processed_folder_id" :form="form" />
                </div>

                <!-- Processed folder name -->
                <div class="mb-3">
                  <label for="processed_folder_name" class="form-label">
                    {{
                      __(
                        'user.googleDriveSettings.fields.processedFolderName.label',
                      )
                    }}
                  </label>
                  <div class="input-group">
                    <input
                      id="processed_folder_name"
                      v-model="form.processed_folder_name"
                      type="text"
                      class="form-control"
                      maxlength="255"
                      :placeholder="
                        __(
                          'user.googleDriveSettings.fields.processedFolderName.placeholder',
                        )
                      "
                      dusk="processed-folder-name"
                    />
                    <button
                      type="button"
                      class="btn btn-outline-secondary"
                      :disabled="
                        !canBrowseFolders ||
                        !form.processed_folder_id ||
                        fetchingProcessedFolderName
                      "
                      data-coreui-toggle="tooltip"
                      data-coreui-placement="top"
                      :title="
                        __(
                          'user.googleDriveSettings.fields.folderName.refetchTitle',
                        )
                      "
                      dusk="button-fetch-processed-folder-name"
                      @click="fetchFolderName('processed')"
                    >
                      <i
                        :class="[
                          'fa',
                          fetchingProcessedFolderName
                            ? 'fa-spinner fa-spin'
                            : 'fa-refresh',
                        ]"
                      ></i>
                    </button>
                    <span
                      class="input-group-text btn btn-outline-input-info"
                      data-coreui-toggle="tooltip"
                      data-coreui-placement="top"
                      :title="
                        __(
                          'user.googleDriveSettings.fields.folderName.infoTooltip',
                        )
                      "
                    >
                      <i class="fa fa-info-circle"></i>
                    </span>
                  </div>
                  <HasError field="processed_folder_name" :form="form" />
                </div>
              </div>
            </div>
          </div>

          <div class="row mb-3">
            <label class="col-form-label col-sm-3">
              {{ __('user.googleDriveSettings.fields.enabled.label') }}
            </label>
            <div class="col-sm-9">
              <div class="form-check form-switch">
                <input
                  id="enabled"
                  v-model="form.enabled"
                  class="form-check-input"
                  type="checkbox"
                />
                <label class="form-check-label" for="enabled">
                  {{ __('user.googleDriveSettings.fields.enabled.helpText') }}
                </label>
              </div>
            </div>
          </div>

          <div class="row mb-3">
            <label for="sync_interval_minutes" class="col-form-label col-sm-3">
              {{ __('user.googleDriveSettings.fields.syncInterval.label') }}
            </label>
            <div class="col-sm-9">
              <div class="input-group">
                <input
                  id="sync_interval_minutes"
                  v-model.number="form.sync_interval_minutes"
                  type="number"
                  class="form-control"
                  name="sync_interval_minutes"
                  min="1"
                  max="1440"
                  dusk="sync-interval-minutes"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __('user.googleDriveSettings.fields.syncInterval.tooltip')
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="sync_interval_minutes" :form="form" />
            </div>
          </div>

          <div v-if="hasConfig" class="row mb-3">
            <label class="col-form-label col-sm-3">
              {{ __('user.googleDriveSettings.fields.lastSync.label') }}
            </label>
            <div class="col-sm-9">
              <p class="form-control-plaintext" dusk="last-sync-at">
                {{ formattedLastSyncAt }}
              </p>
            </div>
          </div>

          <div v-if="hasConfig && lastError" class="row mb-3">
            <label class="col-form-label col-sm-3">
              {{ __('user.googleDriveSettings.fields.lastError.label') }}
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
                      __('user.googleDriveSettings.test.filesFound', {
                        count: testResult.file_count,
                      })
                    }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="card-body">
        <div class="alert alert-warning">
          {{ __('user.googleDriveSettings.sandbox.readOnlyWarning') }}
        </div>
      </div>

      <div v-if="!sandbox_mode && (hasConfig || showForm)" class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <Button
              class="btn btn-primary me-2"
              :form="form"
              dusk="button-save-google-drive"
            >
              <i v-show="!form.busy" class="fa fa-save me-1"></i>
              {{
                hasConfig
                  ? __('user.googleDriveSettings.buttons.update')
                  : __('user.googleDriveSettings.buttons.save')
              }}
            </Button>

            <button
              type="button"
              class="btn btn-secondary me-2"
              :disabled="!canTest || testingConnection"
              dusk="button-test-google-drive"
              @click="testConnection"
            >
              <i
                :class="[
                  'fa me-1',
                  testingConnection ? 'fa-spinner fa-spin' : 'fa-plug',
                ]"
              ></i>
              {{ __('user.googleDriveSettings.buttons.testConnection') }}
            </button>

            <button
              v-if="hasConfig"
              type="button"
              class="btn btn-outline-primary me-2"
              :disabled="syncing"
              dusk="button-sync-google-drive"
              @click="triggerSync"
            >
              <i
                :class="['fa', syncing ? 'fa-spinner fa-spin' : 'fa-sync']"
              ></i>
              {{ __('user.googleDriveSettings.buttons.manualSync') }}
            </button>

            <button
              v-if="!hasConfig && showForm"
              type="button"
              class="btn btn-outline-secondary"
              dusk="button-cancel-add-google-drive"
              @click="cancelAdd"
            >
              <i class="fa fa-times me-1"></i>
              {{ __('user.googleDriveSettings.buttons.cancel') }}
            </button>
          </div>

          <button
            v-if="hasConfig"
            type="button"
            class="btn btn-danger"
            dusk="button-delete-google-drive"
            @click="deleteConfig"
          >
            <i class="fa fa-trash"></i>
            {{ __('user.googleDriveSettings.buttons.deleteConfiguration') }}
          </button>
        </div>
      </div>
    </form>

    <!-- Folder Browser Modal -->
    <div
      id="folderBrowserModal"
      ref="folderBrowserModalEl"
      class="modal fade"
      tabindex="-1"
      aria-labelledby="folderBrowserModalLabel"
      aria-hidden="true"
    >
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="folderBrowserModalLabel" class="modal-title">
              {{ __('user.googleDriveSettings.folderBrowser.title') }}
            </h5>
            <button
              type="button"
              class="btn-close"
              data-coreui-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <div
              v-if="folderBrowserError"
              class="alert alert-danger"
              dusk="folder-browser-error"
            >
              <i class="fa fa-times-circle me-1"></i>
              {{ folderBrowserError }}
            </div>
            <div
              v-else-if="folderBrowserLoading"
              class="text-center py-4"
              dusk="folder-browser-loading"
            >
              <i class="fa fa-spinner fa-spin fa-2x"></i>
              <p class="mt-2 text-muted">
                {{ __('user.googleDriveSettings.folderBrowser.loading') }}
              </p>
            </div>
            <div v-else>
              <p class="text-muted small mb-3">
                <i class="fa fa-info-circle me-1"></i>
                {{ __('user.googleDriveSettings.folderBrowser.sharedHint') }}
              </p>
              <div
                v-if="folderBrowserNotice"
                class="alert alert-warning py-2 mb-3"
                dusk="folder-browser-notice"
              >
                <i class="fa fa-exclamation-triangle me-1"></i>
                {{ folderBrowserNotice }}
              </div>
              <input
                v-model="folderBrowserSearch"
                type="text"
                class="form-control mb-3"
                :placeholder="
                  __('user.googleDriveSettings.folderBrowser.searchPlaceholder')
                "
                dusk="folder-browser-search"
              />
              <div
                v-if="filteredBrowserFolders.length === 0"
                class="text-muted text-center py-3"
                dusk="folder-browser-empty"
              >
                {{ __('user.googleDriveSettings.folderBrowser.empty') }}
              </div>
              <div
                v-else
                class="list-group"
                style="max-height: 360px; overflow-y: auto"
              >
                <button
                  v-for="folder in filteredBrowserFolders"
                  :key="folder.id"
                  type="button"
                  class="list-group-item list-group-item-action d-flex align-items-center"
                  :class="{ active: folderBrowserSelectedId === folder.id }"
                  dusk="folder-browser-item"
                  @click="selectBrowserFolder(folder)"
                >
                  <i class="fa fa-folder me-2"></i>
                  <span class="me-2">{{ folder.name }}</span>
                  <small
                    :class="[
                      'ms-auto font-monospace',
                      folderBrowserSelectedId === folder.id
                        ? 'text-white-50'
                        : 'text-muted',
                    ]"
                    >{{ folder.id }}</small
                  >
                </button>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-outline-secondary"
              data-coreui-dismiss="modal"
              dusk="button-folder-browser-cancel"
            >
              {{ __('user.googleDriveSettings.buttons.cancel') }}
            </button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="!folderBrowserSelectedId"
              dusk="button-folder-browser-confirm"
              @click="confirmFolderSelection"
            >
              {{ __('user.googleDriveSettings.folderBrowser.useFolderButton') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import Form from 'vform';
  import { Button, HasError } from 'vform/src/components/bootstrap5';
  import Swal from 'sweetalert2';

  const DISPOSITION_ACTIONS = [
    {
      key: 'delete',
      label: __('user.googleDriveSettings.postImportActions.delete.label'),
    },
    {
      key: 'trash',
      label: __('user.googleDriveSettings.postImportActions.trash.label'),
    },
    {
      key: 'move_to_processed',
      label: __(
        'user.googleDriveSettings.postImportActions.moveToProcessed.label',
      ),
    },
    {
      key: 'rename_processed',
      label: __(
        'user.googleDriveSettings.postImportActions.renameProcessed.label',
      ),
    },
  ];

  const JSON_FILE_MAX_BYTES = 100 * 1024; // 100 KB

  export default {
    name: 'GoogleDriveSettings',
    components: {
      Button,
      HasError,
    },
    props: {
      aiProcessingEnabled: {
        type: Boolean,
        default: true,
      },
    },
    data: () => ({
      form: new Form({
        service_account_json: '',
        folder_id: '',
        folder_name: '',
        post_import_actions: [],
        processed_folder_id: '',
        processed_folder_name: '',
        enabled: true,
        sync_interval_minutes: 15,
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
      // Folder name fetching
      fetchingFolderName: false,
      fetchingProcessedFolderName: false,
      // Test capabilities
      testCapabilities: null,
      capabilitiesSource: null,
      testNotice: null,
      // JSON file upload
      jsonFileError: null,
      // Folder browser
      folderBrowserModal: null,
      folderBrowserTarget: null,
      folderBrowserFolders: [],
      folderBrowserLoading: false,
      folderBrowserError: null,
      folderBrowserNotice: null,
      folderBrowserSearch: '',
      folderBrowserSelectedId: null,
      folderBrowserSelectedName: null,
      dispositionActions: DISPOSITION_ACTIONS,
    }),
    computed: {
      canBrowseFolders() {
        return (
          this.hasConfig || Boolean(this.form.service_account_json?.trim())
        );
      },
      canTest() {
        return (
          this.form.folder_id &&
          (this.form.service_account_json || this.hasConfig)
        );
      },
      formattedLastSyncAt() {
        if (!this.lastSyncAt) {
          return __('user.googleDriveSettings.common.never');
        }
        try {
          const date = new Date(this.lastSyncAt);
          if (Number.isNaN(date.getTime())) {
            return this.lastSyncAt;
          }
          return date.toLocaleString();
        } catch {
          return this.lastSyncAt;
        }
      },
      moveToProcessedSelected() {
        return (
          Array.isArray(this.form.post_import_actions) &&
          this.form.post_import_actions.includes('move_to_processed')
        );
      },
      processedFolderIdError() {
        if (
          this.moveToProcessedSelected &&
          this.form.folder_id &&
          this.form.processed_folder_id &&
          this.form.processed_folder_id === this.form.folder_id
        ) {
          return __(
            'user.googleDriveSettings.validation.processedFolderMustDiffer',
          );
        }
        return null;
      },
      filteredBrowserFolders() {
        if (!this.folderBrowserSearch) {
          return this.folderBrowserFolders;
        }
        const query = this.folderBrowserSearch.toLowerCase();
        return this.folderBrowserFolders.filter(
          (folder) =>
            folder.name.toLowerCase().includes(query) ||
            folder.id.toLowerCase().includes(query),
        );
      },
    },
    mounted() {
      this.loadConfig();
      initializeBootstrapTooltips(this.$el);

      // Initialize folder browser modal
      this.$nextTick(() => {
        const el = this.$refs.folderBrowserModalEl;
        if (el) {
          if (window.coreui && window.coreui.Modal) {
            this.folderBrowserModal = new window.coreui.Modal(el);
          } else if (window.bootstrap && window.bootstrap.Modal) {
            this.folderBrowserModal = new window.bootstrap.Modal(el);
          }
        }
      });
    },
    updated() {
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
              this.form.folder_id = response.data.folder_id || '';
              this.form.folder_name = response.data.folder_name || '';
              this.form.post_import_actions =
                response.data.post_import_actions || [];
              this.form.processed_folder_id =
                response.data.processed_folder_id || '';
              this.form.processed_folder_name =
                response.data.processed_folder_name || '';
              this.form.enabled = response.data.enabled ?? true;
              this.form.sync_interval_minutes =
                response.data.sync_interval_minutes ?? 15;
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
        const extracted = this.extractFolderId(this.form.folder_id.trim());
        if (extracted) {
          this.form.folder_id = extracted;
        }
      },
      normalizeProcessedFolderId() {
        if (!this.form.processed_folder_id) {
          return;
        }
        const extracted = this.extractFolderId(
          this.form.processed_folder_id.trim(),
        );
        if (extracted) {
          this.form.processed_folder_id = extracted;
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
        this.testCapabilities = null;
        this.capabilitiesSource = null;
        this.testNotice = null;
      },
      toggleServiceAccountVisibility() {
        this.showServiceAccountJson = !this.showServiceAccountJson;
      },

      /**
       * Section 3: JSON Key File Upload
       */
      onJsonFileSelect(event) {
        this.jsonFileError = null;
        const file = event.target.files[0];

        if (!file) {
          return;
        }

        if (file.size > JSON_FILE_MAX_BYTES) {
          this.jsonFileError = __(
            'user.googleDriveSettings.validation.jsonFileTooLarge',
          );
          this.$refs.jsonFileInput.value = '';
          return;
        }

        const reader = new FileReader();

        reader.onload = (e) => {
          const text = e.target.result;
          try {
            JSON.parse(text);
          } catch {
            this.jsonFileError = __(
              'user.googleDriveSettings.validation.jsonFileInvalid',
            );
            this.$refs.jsonFileInput.value = '';
            return;
          }

          this.form.service_account_json = text;
          this.resetTestResult();
          // Always reset the file input after population
          this.$refs.jsonFileInput.value = '';
        };

        reader.onerror = () => {
          this.jsonFileError = __(
            'user.googleDriveSettings.validation.fileReadFailed',
          );
          this.$refs.jsonFileInput.value = '';
        };

        reader.readAsText(file);
      },

      /**
       * Section 1: Folder Name Re-fetch
       * target: 'import' | 'processed'
       */
      async fetchFolderName(target) {
        if (!this.canBrowseFolders) {
          return;
        }

        const folderId =
          target === 'processed'
            ? this.form.processed_folder_id
            : this.form.folder_id;

        if (!folderId) {
          return;
        }

        const isFetching =
          target === 'processed'
            ? 'fetchingProcessedFolderName'
            : 'fetchingFolderName';
        const nameField =
          target === 'processed' ? 'processed_folder_name' : 'folder_name';

        this[isFetching] = true;

        try {
          const response = this.configId
            ? await axios.get(
                this.route('api.v1.google-drive.config.folder-name', {
                  googleDriveConfig: this.configId,
                }),
                { params: { folder_id: folderId } },
              )
            : await axios.post(
                this.route(
                  'api.v1.google-drive.config.folder-name-by-credentials',
                ),
                {
                  folder_id: folderId,
                  service_account_json: this.form.service_account_json.trim(),
                },
              );

          const fetchedName = response.data.folder_name;

          if (!fetchedName) {
            return;
          }

          const existingName = this.form[nameField];

          if (!existingName) {
            this.form[nameField] = fetchedName;
          } else if (existingName === fetchedName) {
            // No action needed
          } else {
            const result = await Swal.fire({
              animation: false,
              text: __(
                'user.googleDriveSettings.dialogs.overwriteFolderName.text',
                { name: fetchedName },
              ),
              icon: 'question',
              showCancelButton: true,
              cancelButtonText: __(
                'user.googleDriveSettings.dialogs.overwriteFolderName.keepExisting',
              ),
              confirmButtonText: __(
                'user.googleDriveSettings.dialogs.overwriteFolderName.overwrite',
              ),
              buttonsStyling: false,
              customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-outline-secondary ms-3',
              },
            });

            if (result.isConfirmed) {
              this.form[nameField] = fetchedName;
            }
          }
        } catch {
          // Silently fail - folder name fetch is optional
        } finally {
          this[isFetching] = false;
        }
      },

      /**
       * Section 2: Capability helpers
       */
      getCapability(actionKey) {
        if (!this.testCapabilities) {
          return undefined;
        }
        if (!(actionKey in this.testCapabilities)) {
          return undefined;
        }
        return this.testCapabilities[actionKey];
      },
      getCapabilityNullReason(actionKey) {
        if (
          actionKey === 'move_to_processed' &&
          !this.form.processed_folder_id
        ) {
          return __(
            'user.googleDriveSettings.capabilities.requiresProcessedFolder',
          );
        }
        return __('user.googleDriveSettings.capabilities.notTestedReason');
      },

      onSubmit() {
        const _vue = this;
        this.form.busy = true;
        this.testResult = null;

        this.normalizeFolderId();
        this.normalizeProcessedFolderId();

        const url = this.hasConfig
          ? this.route('api.v1.google-drive.config.update', {
              googleDriveConfig: this.configId,
            })
          : this.route('api.v1.google-drive.config.store');
        const method = this.hasConfig ? 'patch' : 'post';

        const formData = { ...this.form.data() };
        if (this.hasConfig && !formData.service_account_json) {
          delete formData.service_account_json;
        }

        // Only send processed_folder_id when move_to_processed is selected
        if (!this.moveToProcessedSelected) {
          delete formData.processed_folder_id;
          delete formData.processed_folder_name;
        }

        this.form[method](url, formData)
          .then((response) => {
            if (response.status === 200 || response.status === 201) {
              toastHelpers.showSuccessToast(
                this.hasConfig
                  ? __('user.googleDriveSettings.toasts.updated')
                  : __('user.googleDriveSettings.toasts.created'),
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
                __('user.googleDriveSettings.toasts.validationFailed'),
              );
            } else {
              console.error(error);
              toastHelpers.showErrorToast(
                __('user.googleDriveSettings.toasts.genericError'),
              );
            }
          })
          .finally(() => {
            _vue.form.busy = false;
          });
      },

      testConnection() {
        this.testingConnection = true;
        this.testResult = null;
        this.testCapabilities = null;
        this.capabilitiesSource = null;
        this.testNotice = null;

        this.normalizeFolderId();
        this.normalizeProcessedFolderId();

        const testData = {
          service_account_json:
            this.form.service_account_json || '__existing__',
          folder_id: this.form.folder_id,
        };

        if (this.moveToProcessedSelected && this.form.processed_folder_id) {
          testData.processed_folder_id = this.form.processed_folder_id;
          testData.post_import_actions = this.form.post_import_actions;
        }

        axios
          .post(this.route('api.v1.google-drive.config.test'), testData)
          .then((response) => {
            this.testResult = {
              success: true,
              message:
                response.data.message ||
                __('user.googleDriveSettings.toasts.connectionSuccess'),
              file_count: response.data.file_count ?? 0,
            };

            // Section 1: auto-populate folder name if empty
            if (response.data.folder_name && !this.form.folder_name) {
              this.form.folder_name = response.data.folder_name;
            }

            // Section 2: capabilities
            if (response.data.capabilities) {
              this.testCapabilities = response.data.capabilities;
              this.capabilitiesSource =
                response.data.capabilities_source || null;
              this.testNotice = response.data.notice || null;

              this.$nextTick(() => {
                initializeBootstrapTooltips(this.$el);
              });
            }
          })
          .catch((error) => {
            this.testResult = {
              success: false,
              message:
                error.response?.data?.error?.message ||
                error.response?.data?.message ||
                __('user.googleDriveSettings.toasts.connectionFailed'),
            };
          })
          .finally(() => {
            this.testingConnection = false;
          });
      },

      triggerSync() {
        if (!this.configId) {
          return;
        }

        this.syncing = true;
        axios
          .post(
            this.route('api.v1.google-drive.config.sync', {
              googleDriveConfig: this.configId,
            }),
          )
          .then((response) => {
            toastHelpers.showInfoToast(
              response.data?.message ||
                __('user.googleDriveSettings.toasts.syncQueued'),
            );
          })
          .catch((error) => {
            console.error(error);
            toastHelpers.showErrorToast(
              __('user.googleDriveSettings.toasts.syncFailed'),
            );
          })
          .finally(() => {
            this.syncing = false;
          });
      },

      deleteConfig() {
        Swal.fire({
          animation: false,
          text: this.__(
            'user.googleDriveSettings.dialogs.deleteConfiguration.text',
          ),
          icon: 'warning',
          showCancelButton: true,
          cancelButtonText: this.__('user.googleDriveSettings.buttons.cancel'),
          confirmButtonText: this.__(
            'user.googleDriveSettings.dialogs.deleteConfiguration.confirm',
          ),
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
                  googleDriveConfig: this.configId,
                }),
              )
              .then(() => {
                this.configId = null;
                this.hasConfig = false;
                this.showForm = false;
                this.form.reset();
                this.testResult = null;
                this.testCapabilities = null;
                this.capabilitiesSource = null;
                this.testNotice = null;
                this.serviceAccountEmail = null;
                this.lastSyncAt = null;
                this.lastError = null;

                toastHelpers.showSuccessToast(
                  __('user.googleDriveSettings.toasts.deleted'),
                );
              })
              .catch((error) => {
                console.error(error);
                toastHelpers.showErrorToast(
                  __('user.googleDriveSettings.toasts.deleteFailed'),
                );
              });
          }
        });
      },

      cancelAdd() {
        this.showForm = false;
        this.form.reset();
        this.testResult = null;
        this.testCapabilities = null;
        this.capabilitiesSource = null;
        this.testNotice = null;
      },

      /**
       * Section 4: Folder Browser
       */
      openFolderBrowser(target) {
        if (!this.canBrowseFolders) {
          return;
        }

        this.folderBrowserTarget = target;
        this.folderBrowserFolders = [];
        this.folderBrowserError = null;
        this.folderBrowserNotice = null;
        this.folderBrowserSearch = '';
        this.folderBrowserSelectedId = null;
        this.folderBrowserSelectedName = null;
        this.folderBrowserLoading = true;

        if (this.folderBrowserModal) {
          this.folderBrowserModal.show();
        }

        const folderRequest = this.configId
          ? axios.get(
              this.route('api.v1.google-drive.config.folders', {
                googleDriveConfig: this.configId,
              }),
            )
          : axios.post(
              this.route('api.v1.google-drive.config.folders-by-credentials'),
              {
                service_account_json: this.form.service_account_json.trim(),
              },
            );

        folderRequest
          .then((response) => {
            this.folderBrowserFolders = response.data.folders || [];
            this.folderBrowserNotice = response.data.notice || null;
          })
          .catch((error) => {
            this.folderBrowserError =
              error.response?.data?.error?.message ||
              __('user.googleDriveSettings.folderBrowser.loadFailed');
            this.folderBrowserNotice = null;
          })
          .finally(() => {
            this.folderBrowserLoading = false;
          });
      },

      selectBrowserFolder(folder) {
        this.folderBrowserSelectedId = folder.id;
        this.folderBrowserSelectedName = folder.name;
      },

      confirmFolderSelection() {
        if (!this.folderBrowserSelectedId) {
          return;
        }

        if (this.folderBrowserTarget === 'processed') {
          this.form.processed_folder_id = this.folderBrowserSelectedId;
          this.form.processed_folder_name =
            this.folderBrowserSelectedName || '';
          this.resetTestResult();

          // Trigger name re-fetch to confirm
          this.$nextTick(() => {
            if (!this.form.processed_folder_name) {
              this.fetchFolderName('processed');
            }
          });
        } else {
          this.form.folder_id = this.folderBrowserSelectedId;
          this.form.folder_name = this.folderBrowserSelectedName || '';
          this.resetTestResult();

          // Trigger name re-fetch to confirm
          this.$nextTick(() => {
            if (!this.form.folder_name) {
              this.fetchFolderName('import');
            }
          });
        }

        if (this.folderBrowserModal) {
          this.folderBrowserModal.hide();
        }
      },

      __,
    },
  };
</script>

<style scoped>
  .password-masked {
    -webkit-text-security: disc;
    -moz-text-security: disc;
  }
</style>
