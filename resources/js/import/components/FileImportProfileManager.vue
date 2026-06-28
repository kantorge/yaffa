<template>
  <div class="card mb-3">
    <div
      class="card-header d-flex justify-content-between align-items-center collapse-control"
      data-coreui-toggle="collapse"
      :data-coreui-target="`#${collapseId}`"
      role="button"
    >
      <div class="card-title mb-0">
        <i class="fa fa-angle-down me-1"></i>
        {{
          fileType === 'qif'
            ? __('My QIF field mapping profiles')
            : __('My CSV import profiles')
        }}
      </div>
    </div>

    <div class="collapse card-body show" :id="collapseId">
      <!-- New profile button (shown when not editing or showing wizard) -->
      <div v-if="!editingProfile && !showWizard" class="mb-3">
        <button
          type="button"
          class="btn btn-sm btn-outline-primary"
          @click="startCreate"
        >
          <i class="fa fa-plus me-1"></i>{{ __('New profile') }}
        </button>
      </div>

      <!-- Error -->
      <div
        v-if="error"
        class="alert alert-danger alert-dismissible mb-3"
        role="alert"
      >
        {{ error }}
        <button type="button" class="btn-close" @click="error = null"></button>
      </div>

      <!-- CSV profile creation wizard (new profile) -->
      <div
        v-if="showWizard && fileType === 'csv' && !editingProfile"
        class="border rounded p-3 mb-3 bg-light"
      >
        <ProfileCreationWizard
          :account-id="null"
          :has-ai-provider="hasAiProvider"
          @saved="onWizardSaved"
          @cancel="showWizard = false"
        />
      </div>

      <!-- CSV profile edit wizard (existing profile) -->
      <div
        v-if="showWizard && fileType === 'csv' && editingProfile"
        class="border rounded p-3 mb-3 bg-light"
      >
        <ProfileCreationWizard
          :account-id="null"
          :has-ai-provider="hasAiProvider"
          :edit-profile-id="editingProfile.id"
          :initial-profile="editingProfile._raw"
          @saved="onWizardSaved"
          @cancel="cancelEdit"
        />
      </div>

      <!-- Create / Edit form (QIF always; CSV edit handled by wizard above) -->
      <div v-if="editingProfile && fileType !== 'csv'" class="border rounded p-3 mb-3 bg-light">
        <div class="fw-semibold mb-3">
          {{ editingProfile.id ? __('Edit profile') : __('New profile') }}
        </div>

        <div class="row g-2 mb-2">
          <div class="col-md-6">
            <label class="form-label small">{{ __('Name') }} *</label>
            <input
              v-model="editingProfile.name"
              type="text"
              class="form-control form-control-sm"
              :placeholder="
                fileType === 'qif'
                  ? __('e.g. My Bank QIF')
                  : __('e.g. My Bank CSV')
              "
            />
          </div>
        </div>

        <!-- CSV-specific fields (edit mode only) -->
        <template v-if="fileType === 'csv'">
          <div class="row g-2 mb-2">
            <div class="col-md-3">
              <label class="form-label small">{{ __('Delimiter') }}</label>
              <input
                v-model="editingProfile.delimiter"
                type="text"
                class="form-control form-control-sm"
                maxlength="5"
                placeholder=","
              />
            </div>
            <div class="col-md-3 d-flex align-items-end pb-1">
              <div class="form-check">
                <input
                  id="profile-has-header"
                  v-model="editingProfile.has_header_row"
                  type="checkbox"
                  class="form-check-input"
                />
                <label class="form-check-label small" for="profile-has-header">
                  {{ __('Has header row') }}
                </label>
              </div>
            </div>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label small">{{ __('Date format') }}</label>
              <input
                v-model="editingProfile.date_format"
                type="text"
                class="form-control form-control-sm"
                placeholder="Y-m-d"
              />
            </div>
            <div class="col-md-4">
              <label class="form-label small">{{
                __('Decimal separator')
              }}</label>
              <input
                v-model="editingProfile.decimal_separator"
                type="text"
                class="form-control form-control-sm"
                maxlength="10"
                placeholder="."
              />
            </div>
            <div class="col-md-4">
              <label class="form-label small">{{
                __('Thousand separator')
              }}</label>
              <input
                v-model="editingProfile.thousand_separator"
                type="text"
                class="form-control form-control-sm"
                maxlength="10"
                placeholder=","
              />
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label small">{{ __('Sign handling') }}</label>
            <select
              v-model="editingProfile.sign_handling"
              class="form-select form-select-sm"
            >
              <option :value="null">{{ __('— Default (as-is) —') }}</option>
              <option value="as_is">{{ __('As-is') }}</option>
              <option value="inverted">{{ __('Inverted') }}</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small">
              {{ __('Column mapping (JSON)') }}
              <span class="text-muted fw-normal">{{
                __('— source header: canonical field')
              }}</span>
            </label>
            <textarea
              v-model="editingProfile.mapping_json_text"
              class="form-control form-control-sm font-monospace"
              rows="5"
              :placeholder="mappingJsonPlaceholder"
              @input="validateMappingJson"
            ></textarea>
            <div v-if="mappingJsonError" class="text-danger small mt-1">
              {{ mappingJsonError }}
            </div>
            <div class="form-text">
              {{
                __(
                  'Map each source column header to a canonical field: date, amount, payee, comment, reference, category, ignore.',
                )
              }}
            </div>
          </div>

          <!-- AI re-suggestion panel (edit mode) -->
          <div v-if="hasAiProvider" class="mb-3">
            <button
              v-if="!showEditAiPanel"
              type="button"
              class="btn btn-sm btn-outline-info"
              :disabled="aiSuggesting"
              @click="showEditAiPanel = true"
            >
              <i class="fa fa-magic me-1"></i>{{ __('Re-suggest with AI') }}
            </button>

            <div
              v-if="showEditAiPanel"
              class="border rounded p-3 bg-white mt-2"
            >
              <div class="fw-semibold small mb-2">
                {{ __('AI Profile Re-suggestion') }}
              </div>
              <div class="alert alert-info small mb-2 py-2">
                <i class="fa fa-info-circle me-1"></i>
                {{
                  __(
                    'The first 10 rows of your sample will be sent to your configured AI provider using your API key.',
                  )
                }}
              </div>
              <div class="mb-2">
                <label class="form-label small">{{
                  __('CSV sample file')
                }}</label>
                <input
                  ref="editAiFileInput"
                  type="file"
                  class="form-control form-control-sm"
                  accept=".csv,.txt"
                  :disabled="aiSuggesting"
                  @change="onEditAiFileChange"
                />
              </div>
              <div v-if="aiError" class="alert alert-danger small py-2 mb-2">
                {{ aiError }}
              </div>
              <div class="d-flex gap-2">
                <button
                  type="button"
                  class="btn btn-sm btn-info"
                  :disabled="!editAiFile || aiSuggesting"
                  @click="requestEditAiSuggestion"
                >
                  <span
                    v-if="aiSuggesting"
                    class="spinner-border spinner-border-sm me-1"
                  ></span>
                  {{
                    aiSuggesting
                      ? __('Requesting suggestion…')
                      : __('Get AI suggestion')
                  }}
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary"
                  :disabled="aiSuggesting"
                  @click="showEditAiPanel = false"
                >
                  {{ __('Close') }}
                </button>
              </div>
            </div>
          </div>
        </template>

        <!-- QIF-specific fields -->
        <template v-else-if="fileType === 'qif'">
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label small">{{
                __('Payee field marker')
              }}</label>
              <select
                v-model="editingProfile.payee_marker"
                class="form-select form-select-sm"
              >
                <option value="P">P {{ __('(standard)') }}</option>
                <option value="M">M</option>
                <option value="L">L</option>
                <option value="N">N</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small">{{
                __('Comment field marker')
              }}</label>
              <select
                v-model="editingProfile.comment_marker"
                class="form-select form-select-sm"
              >
                <option value="M">M {{ __('(standard)') }}</option>
                <option value="P">P</option>
                <option value="L">L</option>
                <option value="N">N</option>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label small">{{
                __('Category field marker')
              }}</label>
              <select
                v-model="editingProfile.category_marker"
                class="form-select form-select-sm"
              >
                <option value="L">L {{ __('(standard)') }}</option>
                <option value="P">P</option>
                <option value="M">M</option>
                <option value="N">N</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small">{{
                __('Reference field marker')
              }}</label>
              <select
                v-model="editingProfile.reference_marker"
                class="form-select form-select-sm"
              >
                <option value="N">N {{ __('(standard)') }}</option>
                <option value="P">P</option>
                <option value="M">M</option>
                <option value="L">L</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small">{{ __('Amount sign') }}</label>
            <select
              v-model="editingProfile.amount_sign"
              class="form-select form-select-sm"
            >
              <option value="normal">{{ __('Normal (as-is)') }}</option>
              <option value="inverted">{{ __('Inverted') }}</option>
            </select>
          </div>
        </template>

        <div class="d-flex gap-2">
          <button
            type="button"
            class="btn btn-sm btn-primary"
            :disabled="saving || (fileType === 'csv' && !!mappingJsonError)"
            @click="saveProfile"
          >
            <span
              v-if="saving"
              class="spinner-border spinner-border-sm me-1"
            ></span>
            {{ __('Save') }}
          </button>
          <button
            type="button"
            class="btn btn-sm btn-outline-secondary"
            :disabled="saving"
            @click="cancelEdit"
          >
            {{ __('Cancel') }}
          </button>
        </div>
      </div>

      <!-- User profiles list -->
      <div v-if="loading" class="text-muted small">
        {{ __('Loading profiles…') }}
      </div>

      <div
        v-else-if="userProfiles.length === 0 && !editingProfile && !showWizard"
        class="text-muted small"
      >
        {{
          fileType === 'qif'
            ? __(
                'No custom profiles yet. Create one to define your own QIF field mappings.',
              )
            : __('No custom profiles yet. Use the wizard to create one.')
        }}
      </div>

      <table
        v-else-if="userProfiles.length > 0 && !showWizard"
        class="table table-sm mb-0"
      >
        <thead>
          <tr v-if="fileType === 'csv'">
            <th>{{ __('Name') }}</th>
            <th>{{ __('Delimiter') }}</th>
            <th>{{ __('Date format') }}</th>
            <th></th>
          </tr>
          <tr v-else>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Payee marker') }}</th>
            <th>{{ __('Amount sign') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="profile in userProfiles"
            :key="profile.id"
            :class="{
              'table-active':
                editingProfile && editingProfile.id === profile.id,
            }"
          >
            <template v-if="fileType === 'csv'">
              <td>{{ profile.name }}</td>
              <td class="font-monospace">{{ profile.delimiter || ',' }}</td>
              <td class="text-muted">{{ profile.date_format || '—' }}</td>
            </template>
            <template v-else>
              <td>{{ profile.name }}</td>
              <td class="font-monospace">
                {{ profile.options_json?.field_map?.payee || 'P' }}
              </td>
              <td class="text-muted">
                {{ profile.options_json?.amount_sign || 'normal' }}
              </td>
            </template>
            <td class="text-end text-nowrap">
              <button
                type="button"
                class="btn btn-sm btn-outline-secondary me-1"
                :disabled="!!editingProfile"
                :title="__('Export as JSON')"
                @click="exportProfile(profile)"
                v-if="false"
              >
                <i class="fa fa-download"></i>
              </button>
              <button
                type="button"
                class="btn btn-sm btn-outline-primary me-1"
                :disabled="!!editingProfile"
                @click="startEdit(profile)"
              >
                <i class="fa fa-edit"></i>
              </button>
              <button
                type="button"
                class="btn btn-sm btn-outline-danger"
                :disabled="!!editingProfile || deletingId === profile.id"
                @click="deleteProfile(profile)"
              >
                <span
                  v-if="deletingId === profile.id"
                  class="spinner-border spinner-border-sm"
                ></span>
                <i v-else class="fa fa-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
  import Swal from 'sweetalert2';
  import axios from 'axios';
  import { computed, ref } from 'vue';
  import { __ } from '@/shared/lib/i18n';
  import { escapeHtml } from '@/shared/lib/helpers';
  import ProfileCreationWizard from './ProfileCreationWizard.vue';

  const mappingJsonPlaceholder = JSON.stringify(
    { Date: 'date', Amount: 'amount', Payee: 'payee', Memo: 'comment' },
    null,
    2,
  );

  export default {
    name: 'FileImportProfileManager',
    components: { ProfileCreationWizard },
    props: {
      profiles: {
        type: Array,
        required: true,
      },
      loading: {
        type: Boolean,
        default: false,
      },
      fileType: {
        type: String,
        default: 'csv',
      },
    },
    emits: ['profiles-updated'],
    setup(props, { emit }) {
      const editingProfile = ref(null);
      const saving = ref(false);
      const deletingId = ref(null);
      const error = ref(null);
      const mappingJsonError = ref(null);
      const showWizard = ref(false);

      // AI suggestion state (edit mode)
      const showEditAiPanel = ref(false);
      const editAiFile = ref(null);
      const aiSuggesting = ref(false);
      const aiError = ref(null);

      const hasAiProvider =
        typeof window.hasAiProvider === 'boolean'
          ? window.hasAiProvider
          : false;

      const collapseId = computed(() =>
        props.fileType === 'qif'
          ? 'qifProfileManagerBody'
          : 'csvProfileManagerBody',
      );

      const userProfiles = computed(() =>
        props.profiles.filter((p) => p.type === 'user'),
      );

      const buildEditingState = (profile) => {
        if (props.fileType === 'qif') {
          const fieldMap = profile?.options_json?.field_map || {};
          return {
            id: profile?.id || null,
            name: profile?.name || '',
            payee_marker: fieldMap.payee || 'P',
            comment_marker: fieldMap.comment || 'M',
            category_marker: fieldMap.category || 'L',
            reference_marker: fieldMap.reference || 'N',
            amount_sign: profile?.options_json?.amount_sign || 'normal',
          };
        }

        return {
          id: profile?.id || null,
          name: profile?.name || '',
          delimiter: profile?.delimiter || ',',
          has_header_row: profile?.has_header_row !== false,
          date_format: profile?.date_format || null,
          decimal_separator: profile?.decimal_separator || null,
          thousand_separator: profile?.thousand_separator || null,
          sign_handling: profile?.sign_handling || null,
          mapping_json_text: JSON.stringify(
            profile?.mapping_json || {},
            null,
            2,
          ),
        };
      };

      const startCreate = () => {
        mappingJsonError.value = null;
        error.value = null;
        if (props.fileType === 'csv') {
          showWizard.value = true;
        } else {
          editingProfile.value = buildEditingState(null);
        }
      };

      const startEdit = (profile) => {
        mappingJsonError.value = null;
        error.value = null;
        showEditAiPanel.value = false;
        editAiFile.value = null;
        aiError.value = null;

        const state = buildEditingState(profile);
        state._raw = profile;
        editingProfile.value = state;

        // CSV profiles use the wizard; QIF profiles use the form
        showWizard.value = props.fileType === 'csv';
      };

      const cancelEdit = () => {
        editingProfile.value = null;
        showWizard.value = false;
        mappingJsonError.value = null;
        error.value = null;
        showEditAiPanel.value = false;
        aiError.value = null;
      };

      const onWizardSaved = () => {
        showWizard.value = false;
        editingProfile.value = null;
        emit('profiles-updated');
      };

      const validateMappingJson = () => {
        if (!editingProfile.value) return;
        const text = editingProfile.value.mapping_json_text.trim();
        if (!text) {
          mappingJsonError.value = __('Column mapping is required.');
          return;
        }
        try {
          const parsed = JSON.parse(text);
          if (typeof parsed !== 'object' || Array.isArray(parsed)) {
            mappingJsonError.value = __(
              'Must be a JSON object mapping column names to field names.',
            );
            return;
          }
          mappingJsonError.value = null;
        } catch (_e) {
          mappingJsonError.value = __('Invalid JSON. Please check the format.');
        }
      };

      const saveProfile = async () => {
        if (!editingProfile.value) return;

        let payload;

        if (props.fileType === 'qif') {
          payload = {
            name: editingProfile.value.name,
            options_json: {
              field_map: {
                payee: editingProfile.value.payee_marker,
                comment: editingProfile.value.comment_marker,
                category: editingProfile.value.category_marker,
                reference: editingProfile.value.reference_marker,
              },
              amount_sign: editingProfile.value.amount_sign,
            },
          };
          if (!editingProfile.value.id) payload.file_type = 'qif';
        } else {
          validateMappingJson();
          if (mappingJsonError.value) return;

          let mappingJson;
          try {
            mappingJson = JSON.parse(editingProfile.value.mapping_json_text);
          } catch (_e) {
            mappingJsonError.value = __(
              'Invalid JSON. Please check the format.',
            );
            return;
          }

          payload = {
            name: editingProfile.value.name,
            delimiter: editingProfile.value.delimiter || ',',
            has_header_row: editingProfile.value.has_header_row,
            date_format: editingProfile.value.date_format || null,
            decimal_separator: editingProfile.value.decimal_separator || null,
            thousand_separator: editingProfile.value.thousand_separator || null,
            sign_handling: editingProfile.value.sign_handling || null,
            mapping_json: mappingJson,
          };
        }

        saving.value = true;
        error.value = null;

        try {
          if (editingProfile.value.id) {
            await axios.patch(
              `/api/v1/imports/file-profiles/${editingProfile.value.id}`,
              payload,
            );
          } else {
            await axios.post('/api/v1/imports/file-profiles', payload);
          }
          editingProfile.value = null;
          mappingJsonError.value = null;
          emit('profiles-updated');
        } catch (err) {
          if (err?.response?.data?.errors) {
            const firstKey = Object.keys(err.response.data.errors)[0];
            error.value =
              err.response.data.errors[firstKey]?.[0] ||
              __('Save failed. Please review your input.');
          } else if (err?.response?.data?.error?.message) {
            error.value = err.response.data.error.message;
          } else {
            error.value = __('Save failed due to a network or server error.');
          }
        } finally {
          saving.value = false;
        }
      };

      const deleteProfile = async (profile) => {
        let affectedAccounts = [];
        try {
          const response = await axios.get(
            `/api/v1/imports/file-profiles/${profile.id}/affected-accounts`,
          );
          affectedAccounts = Array.isArray(response.data?.data)
            ? response.data.data
            : [];
        } catch (_e) {
          // Non-critical; proceed without affected-account context
        }

        const escapedName = escapeHtml(profile.name);
        let html =
          `<p>` +
          __('Delete profile ":name"? This cannot be undone.', {
            name: escapedName,
          }) +
          `</p>`;

        if (affectedAccounts.length > 0) {
          const accountList = affectedAccounts
            .map((a) => `<strong>${escapeHtml(a.name)}</strong>`)
            .join(', ');
          html +=
            `<p class="small mt-2">` +
            __(
              'This profile is set as the default for :count account(s): :accounts. The default will be removed — manual profile selection will be required for these accounts on future imports.',
              { count: affectedAccounts.length, accounts: accountList },
            ) +
            `</p>`;
        }

        const result = await Swal.fire({
          html,
          icon: 'warning',
          showCancelButton: true,
          cancelButtonText: __('Cancel'),
          confirmButtonText: __('Delete'),
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary ms-3',
          },
        });

        if (!result.isConfirmed) {
          return;
        }

        deletingId.value = profile.id;
        error.value = null;

        try {
          await axios.delete(`/api/v1/imports/file-profiles/${profile.id}`);
          emit('profiles-updated');
        } catch (err) {
          if (err?.response?.data?.error?.message) {
            error.value = err.response.data.error.message;
          } else {
            error.value = __('Delete failed. Please try again.');
          }
        } finally {
          deletingId.value = null;
        }
      };

      const exportProfile = (profile) => {
        const exportData = {
          type: profile.type,
          file_type: profile.file_type,
          name: profile.name,
          delimiter: profile.delimiter,
          has_header_row: profile.has_header_row,
          date_format: profile.date_format,
          decimal_separator: profile.decimal_separator,
          thousand_separator: profile.thousand_separator,
          sign_handling: profile.sign_handling,
          mapping_json: profile.mapping_json,
          options_json: profile.options_json,
          active: profile.active,
        };

        const blob = new Blob([JSON.stringify(exportData, null, 2)], {
          type: 'application/json',
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${profile.name.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_profile.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      };

      // ── AI re-suggestion (edit mode) ──────────────────────────────────────

      const onEditAiFileChange = (event) => {
        editAiFile.value = event.target.files?.[0] || null;
        aiError.value = null;
      };

      const requestEditAiSuggestion = async () => {
        if (!editAiFile.value || !editingProfile.value) return;

        aiSuggesting.value = true;
        aiError.value = null;

        try {
          const formData = new FormData();
          formData.append('file', editAiFile.value);

          const response = await axios.post(
            '/api/v1/imports/file-profiles/suggest',
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
          );

          const data = response.data.data;

          // Apply settings to edit form
          if (data.delimiter) editingProfile.value.delimiter = data.delimiter;
          if (typeof data.has_header_row === 'boolean')
            editingProfile.value.has_header_row = data.has_header_row;
          if (data.date_format !== undefined)
            editingProfile.value.date_format = data.date_format;
          if (data.decimal_separator)
            editingProfile.value.decimal_separator = data.decimal_separator;
          if (data.thousand_separator !== undefined)
            editingProfile.value.thousand_separator = data.thousand_separator;
          if (data.sign_handling)
            editingProfile.value.sign_handling = data.sign_handling;
          if (data.mapping_json && typeof data.mapping_json === 'object') {
            editingProfile.value.mapping_json_text = JSON.stringify(
              data.mapping_json,
              null,
              2,
            );
            mappingJsonError.value = null;
          }

          showEditAiPanel.value = false;
        } catch (err) {
          if (err?.response?.status === 422) {
            const errors = err.response.data?.errors;
            if (errors) {
              const firstKey = Object.keys(errors)[0];
              aiError.value = errors[firstKey]?.[0] || __('Invalid request.');
            } else {
              aiError.value =
                err.response.data?.error?.message ||
                err.response.data?.message ||
                __('Invalid request.');
            }
          } else {
            aiError.value =
              err?.response?.data?.error?.message ||
              err?.response?.data?.message ||
              __('AI provider request failed. Please try again.');
          }
        } finally {
          aiSuggesting.value = false;
        }
      };

      return {
        editingProfile,
        saving,
        deletingId,
        error,
        mappingJsonError,
        mappingJsonPlaceholder,
        showWizard,
        hasAiProvider,
        showEditAiPanel,
        editAiFile,
        aiSuggesting,
        aiError,
        collapseId,
        userProfiles,
        startCreate,
        startEdit,
        cancelEdit,
        onWizardSaved,
        validateMappingJson,
        saveProfile,
        deleteProfile,
        exportProfile,
        onEditAiFileChange,
        requestEditAiSuggestion,
        __,
      };
    },
  };
</script>
