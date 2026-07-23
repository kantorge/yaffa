<template>
  <div class="card" id="apiTokenManager">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="card-title mb-0">{{ __('API Tokens') }}</div>
      <button
        type="button"
        class="btn btn-primary btn-sm"
        dusk="button-create-api-token"
        @click="openCreateModal"
      >
        <i class="fa fa-plus"></i>
        {{ __('Create Token') }}
      </button>
    </div>
    <div class="card-body">
      <p class="text-muted">
        {{
          __(
            'Personal access tokens let you call the YAFFA API from scripts or other applications, using the same permissions you scope them with. Treat a token like a password.',
          )
        }}
      </p>

      <div v-if="loading" class="text-muted">
        {{ __('Loading tokens...') }}
      </div>

      <div
        v-else-if="loadError"
        class="alert alert-danger mb-0"
        role="alert"
      >
        {{ __('Unable to load API tokens.') }}
      </div>

      <div
        v-else-if="tokens.length === 0"
        class="alert alert-info mb-0"
        role="alert"
      >
        {{ __('You have not created any API tokens yet.') }}
      </div>

      <div v-else class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Abilities') }}</th>
              <th>{{ __('Last used') }}</th>
              <th>{{ __('Expires') }}</th>
              <th class="text-end">{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="token in tokens" :key="token.id">
              <td>{{ token.name }}</td>
              <td>
                <span
                  v-for="ability in token.abilities"
                  :key="ability"
                  class="badge text-bg-secondary me-1"
                >
                  {{ ability }}
                </span>
              </td>
              <td>{{ formatDate(token.last_used_at) }}</td>
              <td>{{ formatDate(token.expires_at) }}</td>
              <td class="text-end">
                <button
                  type="button"
                  class="btn btn-outline-danger btn-sm"
                  :disabled="revokingId === token.id"
                  dusk="button-revoke-api-token"
                  @click="revokeToken(token)"
                >
                  <i
                    :class="[
                      'fa',
                      revokingId === token.id ? 'fa-spinner fa-spin' : 'fa-trash',
                    ]"
                  ></i>
                  {{ __('Revoke') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Token Modal -->
    <div
      id="apiTokenCreateModal"
      ref="createModalEl"
      class="modal fade"
      tabindex="-1"
      aria-labelledby="apiTokenCreateModalLabel"
      aria-hidden="true"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="apiTokenCreateModalLabel" class="modal-title">
              {{ __('Create API Token') }}
            </h5>
            <button
              type="button"
              class="btn-close"
              :disabled="creating || (createdToken && !acknowledged)"
              data-coreui-dismiss="modal"
              aria-label="Close"
              @click="resetForm"
            ></button>
          </div>

          <div class="modal-body">
            <!-- Plaintext token reveal, shown once after creation -->
            <div v-if="createdToken">
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle me-1"></i>
                {{
                  __(
                    'Copy this token now. For your security, it will not be shown again.',
                  )
                }}
              </div>
              <div class="input-group mb-3">
                <input
                  type="text"
                  class="form-control font-monospace"
                  readonly
                  :value="createdToken"
                  dusk="api-token-value"
                />
                <button
                  type="button"
                  class="btn btn-outline-secondary"
                  @click="copyToken"
                >
                  <i class="fa fa-copy"></i>
                  {{ __('Copy') }}
                </button>
              </div>
              <div class="form-check">
                <input
                  id="apiTokenAcknowledge"
                  v-model="acknowledged"
                  class="form-check-input"
                  type="checkbox"
                />
                <label class="form-check-label" for="apiTokenAcknowledge">
                  {{ __('I have copied or saved this token.') }}
                </label>
              </div>
            </div>

            <!-- Creation form -->
            <form v-else @submit.prevent="createToken">
              <div class="mb-3">
                <label for="apiTokenName" class="form-label">
                  {{ __('Name') }}
                </label>
                <input
                  id="apiTokenName"
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': errors.name }"
                  :placeholder="__('e.g. Budgeting script')"
                  required
                />
                <div v-if="errors.name" class="invalid-feedback">
                  {{ errors.name[0] }}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">{{ __('Access') }}</label>
                <div class="btn-group w-100 mb-2" role="group">
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :class="{ active: preset === 'read' }"
                    @click="applyPreset('read')"
                  >
                    {{ __('Read-only') }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :class="{ active: preset === 'full' }"
                    @click="applyPreset('full')"
                  >
                    {{ __('Full access') }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :class="{ active: preset === 'advanced' }"
                    @click="preset = 'advanced'"
                  >
                    {{ __('Advanced') }}
                  </button>
                </div>

                <div v-if="preset === 'advanced'" class="row g-2 border rounded p-2">
                  <div
                    v-for="ability in abilityOptions"
                    :key="ability.value"
                    class="col-6"
                  >
                    <div class="form-check">
                      <input
                        :id="`ability-${ability.value}`"
                        v-model="form.abilities"
                        class="form-check-input"
                        type="checkbox"
                        :value="ability.value"
                      />
                      <label
                        class="form-check-label"
                        :for="`ability-${ability.value}`"
                      >
                        {{ ability.label }}
                      </label>
                    </div>
                  </div>
                </div>
                <div v-if="errors.abilities" class="text-danger small mt-1">
                  {{ errors.abilities[0] }}
                </div>
              </div>

              <div class="mb-3">
                <label for="apiTokenExpiresAt" class="form-label">
                  {{ __('Expires on') }}
                </label>
                <input
                  id="apiTokenExpiresAt"
                  v-model="form.expires_at"
                  type="date"
                  class="form-control"
                  :class="{ 'is-invalid': errors.expires_at }"
                  :min="minExpiryDate"
                />
                <div v-if="errors.expires_at" class="invalid-feedback">
                  {{ errors.expires_at[0] }}
                </div>
                <div class="form-text">
                  {{ __('Leave blank to use the maximum allowed lifetime.') }}
                </div>
              </div>
            </form>
          </div>

          <div class="modal-footer">
            <button
              v-if="createdToken"
              type="button"
              class="btn btn-primary"
              :disabled="!acknowledged"
              data-coreui-dismiss="modal"
              @click="resetForm"
            >
              {{ __('Close') }}
            </button>
            <template v-else>
              <button
                type="button"
                class="btn btn-outline-secondary"
                data-coreui-dismiss="modal"
                @click="resetForm"
              >
                {{ __('Cancel') }}
              </button>
              <button
                type="button"
                class="btn btn-primary"
                :disabled="creating"
                @click="createToken"
              >
                <i
                  :class="['fa', creating ? 'fa-spinner fa-spin' : 'fa-plus']"
                ></i>
                {{ __('Create Token') }}
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';
  import Swal from 'sweetalert2';

  const ABILITY_OPTIONS = [
    { value: 'accounts:read', label: __('Accounts: read') },
    { value: 'accounts:write', label: __('Accounts: write') },
    { value: 'transactions:read', label: __('Transactions: read') },
    { value: 'transactions:write', label: __('Transactions: write') },
    { value: 'investments:read', label: __('Investments: read') },
    { value: 'investments:write', label: __('Investments: write') },
    { value: 'categories:read', label: __('Categories: read') },
    { value: 'categories:write', label: __('Categories: write') },
    { value: 'payees:read', label: __('Payees: read') },
    { value: 'payees:write', label: __('Payees: write') },
    { value: 'tags:read', label: __('Tags: read') },
    { value: 'tags:write', label: __('Tags: write') },
    { value: 'reports:read', label: __('Reports: read') },
    { value: 'imports:write', label: __('Imports: write') },
    { value: 'settings:write', label: __('Settings: write') },
  ];

  export default {
    name: 'ApiTokenManager',
    data: () => ({
      tokens: [],
      loading: false,
      loadError: false,
      revokingId: null,
      abilityOptions: ABILITY_OPTIONS,
      preset: 'read',
      creating: false,
      createdToken: null,
      acknowledged: false,
      createModal: null,
      form: {
        name: '',
        abilities: [],
        expires_at: '',
      },
      errors: {},
    }),
    computed: {
      minExpiryDate() {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        return tomorrow.toISOString().slice(0, 10);
      },
    },
    mounted() {
      this.loadTokens();

      this.$nextTick(() => {
        const el = this.$refs.createModalEl;
        if (el) {
          this.createModal = new window.coreui.Modal(el);
        }
      });

      this.applyPreset('read');
    },
    methods: {
      async loadTokens() {
        this.loading = true;
        this.loadError = false;

        try {
          const response = await axios.get(
            this.route('api.v1.users.me.tokens.index'),
          );
          this.tokens = response.data?.data || [];
        } catch (error) {
          console.error(error);
          this.loadError = true;
          toastHelpers.showErrorToast(__('Unable to load API tokens.'));
        } finally {
          this.loading = false;
        }
      },
      formatDate(value) {
        return value ? new Date(value).toLocaleString() : __('Never');
      },
      applyPreset(preset) {
        this.preset = preset;

        if (preset === 'read') {
          this.form.abilities = this.abilityOptions
            .filter((ability) => ability.value.endsWith(':read'))
            .map((ability) => ability.value);
        } else if (preset === 'full') {
          this.form.abilities = this.abilityOptions.map(
            (ability) => ability.value,
          );
        }
      },
      openCreateModal() {
        this.resetForm();
        this.applyPreset('read');

        if (this.createModal) {
          this.createModal.show();
        }
      },
      resetForm() {
        this.form = { name: '', abilities: [], expires_at: '' };
        this.errors = {};
        this.createdToken = null;
        this.acknowledged = false;
        this.creating = false;
      },
      async createToken() {
        if (this.creating) {
          return;
        }

        this.creating = true;
        this.errors = {};

        try {
          const response = await axios.post(
            this.route('api.v1.users.me.tokens.store'),
            {
              name: this.form.name,
              abilities: this.form.abilities,
              expires_at: this.form.expires_at || null,
            },
          );

          this.createdToken = response.data.token;
          await this.loadTokens();
        } catch (error) {
          if (error.response?.status === 422) {
            this.errors = error.response.data.errors || {};
          } else {
            console.error(error);
            toastHelpers.showErrorToast(__('Unable to create API token.'));
          }
        } finally {
          this.creating = false;
        }
      },
      async copyToken() {
        if (!this.createdToken) {
          return;
        }

        try {
          await navigator.clipboard.writeText(this.createdToken);
          toastHelpers.showSuccessToast(__('Token copied to clipboard.'));
        } catch (error) {
          console.error(error);
          toastHelpers.showErrorToast(__('Unable to copy the token automatically. Please copy it manually.'));
        }
      },
      async revokeToken(token) {
        const result = await Swal.fire({
          animation: false,
          text: __(
            'Are you sure you want to revoke this token? Anything using it will immediately lose access.',
          ),
          icon: 'warning',
          showCancelButton: true,
          cancelButtonText: __('Cancel'),
          confirmButtonText: __('Confirm'),
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary ms-3',
          },
        });

        if (!result.isConfirmed) {
          return;
        }

        this.revokingId = token.id;

        try {
          await axios.delete(
            this.route('api.v1.users.me.tokens.destroy', { id: token.id }),
          );
          toastHelpers.showSuccessToast(__('Token revoked.'));
          await this.loadTokens();
        } catch (error) {
          console.error(error);
          toastHelpers.showErrorToast(__('Unable to revoke the token.'));
        } finally {
          this.revokingId = null;
        }
      },
      __,
    },
  };
</script>
