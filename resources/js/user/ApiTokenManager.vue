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
                <div class="form-text mb-2">
                  {{ __('Every token can always read your data.') }}
                </div>
                <div class="form-check">
                  <input
                    id="apiTokenAbilityWrite"
                    v-model="form.write"
                    class="form-check-input"
                    type="checkbox"
                  />
                  <label class="form-check-label" for="apiTokenAbilityWrite">
                    {{ __('Allow write access') }}
                  </label>
                </div>
                <div class="form-check">
                  <input
                    id="apiTokenAbilitySettings"
                    v-model="form.settings"
                    class="form-check-input"
                    type="checkbox"
                  />
                  <label class="form-check-label" for="apiTokenAbilitySettings">
                    {{ __('Allow account & security settings changes') }}
                  </label>
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

  export default {
    name: 'ApiTokenManager',
    data: () => ({
      tokens: [],
      loading: false,
      loadError: false,
      revokingId: null,
      creating: false,
      createdToken: null,
      acknowledged: false,
      createModal: null,
      form: {
        name: '',
        write: false,
        settings: false,
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
      openCreateModal() {
        this.resetForm();

        if (this.createModal) {
          this.createModal.show();
        }
      },
      resetForm() {
        this.form = { name: '', write: false, settings: false, expires_at: '' };
        this.errors = {};
        this.createdToken = null;
        this.acknowledged = false;
        this.creating = false;
        this.setModalDismissible(true);
      },
      // While the plaintext token is revealed, the modal must not be dismissable via
      // backdrop click or Escape - only the acknowledgement-gated buttons may close it,
      // since the token can never be re-fetched after this.
      setModalDismissible(dismissible) {
        if (!this.createModal) {
          return;
        }
        this.createModal._config.backdrop = dismissible ? true : 'static';
        this.createModal._config.keyboard = dismissible;
      },
      async createToken() {
        if (this.creating) {
          return;
        }

        this.creating = true;
        this.errors = {};

        const abilities = ['read'];
        if (this.form.write) {
          abilities.push('write');
        }
        if (this.form.settings) {
          abilities.push('settings');
        }

        try {
          const response = await axios.post(
            this.route('api.v1.users.me.tokens.store'),
            {
              name: this.form.name,
              abilities,
              expires_at: this.form.expires_at || null,
            },
          );

          this.createdToken = response.data.token;
          this.setModalDismissible(false);
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
