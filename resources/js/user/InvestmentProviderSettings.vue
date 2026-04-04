<template>
  <div id="investmentProviderSettings">
    <div v-if="loading" class="text-muted">
      {{ __('Loading provider configuration...') }}
    </div>

    <div
      v-else-if="providers.length === 0"
      class="alert alert-info"
      role="alert"
    >
      {{ __('No investment price providers are currently available.') }}
    </div>

    <div v-else class="card mb-3">
      <div class="card-header d-flex justify-content-between">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
          <li
            v-for="provider in providers"
            :key="`${provider.key}-tab`"
            class="nav-item"
            role="presentation"
          >
            <button
              type="button"
              class="nav-link d-flex align-items-center gap-2"
              :class="{ active: activeProviderKey === provider.key }"
              role="tab"
              :id="`nav-provider-tab-${provider.key}`"
              :aria-selected="activeProviderKey === provider.key"
              :aria-controls="`provider-tab-panel-${provider.key}`"
              @click="activeProviderKey = provider.key"
            >
              <span>{{ provider.displayName }}</span>
              <span class="badge" :class="badgeClass(provider)">
                {{ provider.statusLabel }}
              </span>
            </button>
          </li>
        </ul>
        <span
          class="fa fa-info-circle text-info"
          :title="
            __(
              'Configure your account-level credentials for providers that need API access. Credentials are encrypted before storage.',
            )
          "
          data-coreui-toggle="tooltip"
          data-coreui-placement="top"
        ></span>
      </div>

      <div class="card-body">
        <div class="tab-content">
          <div
            v-for="provider in providers"
            :key="provider.key"
            class="tab-pane fade"
            :class="{
              show: activeProviderKey === provider.key,
              active: activeProviderKey === provider.key,
            }"
            :id="`provider-tab-panel-${provider.key}`"
            role="tabpanel"
            :aria-labelledby="`nav-provider-tab-${provider.key}`"
            tabindex="0"
          >
            <div
              class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 pb-3 mb-4 border-bottom"
            >
              <div>
                <h3 class="mb-1">{{ provider.displayName }}</h3>
                <p class="text-muted mb-2">{{ provider.description }}</p>
              </div>
              <span
                class="badge align-self-start"
                :class="badgeClass(provider)"
              >
                {{ provider.statusLabel }}
              </span>
            </div>

            <div
              v-if="!providerSupportsAccountConfig(provider)"
              class="alert alert-info"
              role="alert"
            >
              {{ provider.statusDescription }}
            </div>

            <div v-else>
              <div class="row g-4">
                <div
                  v-for="field in credentialFields(provider)"
                  :key="`${provider.key}-${field.key}`"
                  class="col-12 col-lg-6"
                >
                  <label
                    :for="`${provider.key}-${field.key}`"
                    class="form-label"
                  >
                    {{ field.schema.label || humanize(field.key) }}
                  </label>
                  <input
                    :id="`${provider.key}-${field.key}`"
                    v-model="forms[provider.key].credentials[field.key]"
                    :type="credentialInputType(field.key)"
                    class="form-control"
                    :placeholder="credentialPlaceholder(provider, field.key)"
                  />
                  <div class="form-text mt-2" v-if="provider.instructions">
                    {{ provider.instructions }}
                  </div>
                </div>

                <div
                  v-if="provider.rateLimitPolicy?.overrideable"
                  class="col-12 col-lg-6"
                >
                  <div class="row g-3">
                    <div class="col-12 col-sm-6">
                      <label
                        :for="`${provider.key}-per-minute`"
                        class="form-label"
                      >
                        {{ __('Rate limit per minute') }}
                      </label>
                      <input
                        :id="`${provider.key}-per-minute`"
                        v-model="
                          forms[provider.key].rateLimitOverrides.perMinute
                        "
                        class="form-control"
                        min="1"
                        type="number"
                        :placeholder="
                          provider.rateLimitPolicy?.perMinute != null
                            ? String(provider.rateLimitPolicy.perMinute)
                            : __('No limit')
                        "
                      />
                    </div>
                    <div class="col-12 col-sm-6">
                      <label
                        :for="`${provider.key}-per-day`"
                        class="form-label"
                      >
                        {{ __('Rate limit per day') }}
                      </label>
                      <input
                        :id="`${provider.key}-per-day`"
                        v-model="forms[provider.key].rateLimitOverrides.perDay"
                        class="form-control"
                        min="1"
                        type="number"
                        :placeholder="
                          provider.rateLimitPolicy?.perDay != null
                            ? String(provider.rateLimitPolicy.perDay)
                            : __('No limit')
                        "
                      />
                    </div>
                  </div>
                  <div class="form-text mt-2">
                    {{
                      __(
                        'Leave override fields blank to keep the provider defaults. Placeholder values show the built-in defaults, which may not reflect your current plan.',
                      )
                    }}
                  </div>
                </div>
              </div>

              <div
                v-if="provider.currentConfig?.has_credentials"
                class="form-text mt-4"
              >
                {{
                  __(
                    'Saved credentials are hidden. Leave the field blank to keep the current value.',
                  )
                }}
              </div>

              <div
                v-if="provider.currentConfig?.last_error"
                class="alert alert-danger mt-3 mb-0"
                role="alert"
              >
                {{ provider.currentConfig.last_error }}
              </div>

              <div
                v-if="actionMessages[provider.key]"
                class="alert mt-3 mb-0"
                :class="
                  actionMessages[provider.key].type === 'success'
                    ? 'alert-success'
                    : 'alert-danger'
                "
                role="alert"
              >
                {{ actionMessages[provider.key].message }}
              </div>

              <div
                class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top"
              >
                <div class="d-flex gap-2">
                  <button
                    type="button"
                    class="btn btn-primary me-2"
                    :disabled="
                      savingProviderKey === provider.key ||
                      deletingProviderKey === provider.key
                    "
                    @click="saveProvider(provider)"
                  >
                    <i
                      :class="[
                        'fa',
                        savingProviderKey === provider.key
                          ? 'fa-spinner fa-spin'
                          : 'fa-save',
                      ]"
                    ></i>
                    {{ __('Save') }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-secondary"
                    :disabled="
                      testingProviderKey === provider.key ||
                      deletingProviderKey === provider.key
                    "
                    @click="testProvider(provider)"
                  >
                    <i
                      :class="[
                        'fa',
                        testingProviderKey === provider.key
                          ? 'fa-spinner fa-spin'
                          : 'fa-plug',
                      ]"
                    ></i>
                    {{ __('Test connection') }}
                  </button>
                </div>

                <button
                  v-if="provider.currentConfig"
                  type="button"
                  class="btn btn-danger"
                  :disabled="deletingProviderKey === provider.key"
                  @click="removeProvider(provider)"
                >
                  <i
                    :class="[
                      'fa',
                      deletingProviderKey === provider.key
                        ? 'fa-spinner fa-spin'
                        : 'fa-trash',
                    ]"
                  ></i>
                  {{ __('Remove') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { nextTick } from 'vue';
  import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import Swal from 'sweetalert2';

  export default {
    name: 'InvestmentProviderSettings',
    data: () => ({
      providers: [],
      testingProviderKey: null,
      activeProviderKey: null,
      forms: {},
      loading: false,
      savingProviderKey: null,
      deletingProviderKey: null,
      actionMessages: {},
    }),
    mounted() {
      this.loadProviders();
    },
    methods: {
      async loadProviders() {
        this.loading = true;

        try {
          const response = await axios.get(
            `${this.route('api.v1.investment-price-providers.available')}?include_unavailable=1`,
          );
          this.providers = Array.isArray(response.data) ? response.data : [];

          if (
            !this.activeProviderKey &&
            Array.isArray(this.providers) &&
            this.providers.length > 0
          ) {
            this.activeProviderKey = this.providers[0].key;
          }

          if (
            this.activeProviderKey &&
            !this.providers.find(
              (provider) => provider.key === this.activeProviderKey,
            )
          ) {
            this.activeProviderKey = this.providers[0]?.key || null;
          }

          this.forms = this.providers.reduce((carry, provider) => {
            carry[provider.key] = this.formStateForProvider(
              provider,
              this.forms?.[provider.key],
            );
            return carry;
          }, {});

          await nextTick();
          initializeBootstrapTooltips(this.$el);
        } catch (error) {
          console.error(error);
          toastHelpers.showErrorToast(
            __('Unable to load investment provider configuration.'),
          );
        } finally {
          this.loading = false;
        }
      },
      formStateForProvider(provider, previousForm = null) {
        const currentConfig = provider.currentConfig || {};
        const rateLimitOverrides = currentConfig.rate_limit_overrides || {};

        return {
          enabled: previousForm?.enabled ?? currentConfig.enabled ?? true,
          credentials: this.credentialFields(provider).reduce(
            (carry, field) => {
              carry[field.key] = previousForm?.credentials?.[field.key] || '';
              return carry;
            },
            {},
          ),

          rateLimitOverrides: {
            perMinute:
              previousForm?.rateLimitOverrides?.perMinute ??
              rateLimitOverrides.perMinute ??
              '',
            perDay:
              previousForm?.rateLimitOverrides?.perDay ??
              rateLimitOverrides.perDay ??
              '',
          },
        };
      },
      providerSupportsAccountConfig(provider) {
        return (
          this.credentialFields(provider).length > 0 ||
          Boolean(provider.rateLimitPolicy?.overrideable)
        );
      },
      credentialFields(provider) {
        return Object.entries(
          provider.userSettingsSchema?.properties || {},
        ).map(([key, schema]) => ({ key, schema }));
      },
      credentialInputType(fieldKey) {
        return /(key|token|secret|password)/i.test(fieldKey)
          ? 'password'
          : 'text';
      },
      credentialPlaceholder(provider, fieldKey) {
        if (provider.currentConfig?.has_credentials) {
          return __('Leave blank to keep current value');
        }

        return __('Enter value');
      },
      badgeClass(provider) {
        const reasonFlags = provider.reasonFlags || [];

        if (reasonFlags.includes('disabled')) {
          return 'text-bg-secondary';
        }

        if (
          reasonFlags.includes('setup_required') ||
          reasonFlags.includes('missing_credentials')
        ) {
          return 'text-bg-warning';
        }

        return 'text-bg-success';
      },
      payloadForProvider(provider) {
        const form = this.forms[provider.key];
        const credentials = Object.entries(form.credentials).reduce(
          (carry, [field, value]) => {
            if (value !== '') {
              carry[field] = value;
            }
            return carry;
          },
          {},
        );

        const payload = {
          enabled: form.enabled,
        };

        if (Object.keys(credentials).length > 0) {
          payload.credentials = credentials;
        }

        if (provider.rateLimitPolicy?.overrideable) {
          const overrides = {};

          if (form.rateLimitOverrides.perMinute !== '') {
            overrides.perMinute = Number(form.rateLimitOverrides.perMinute);
          }

          if (form.rateLimitOverrides.perDay !== '') {
            overrides.perDay = Number(form.rateLimitOverrides.perDay);
          }

          if (Object.keys(overrides).length > 0) {
            payload.rate_limit_overrides = overrides;
          }
        }

        return payload;
      },
      async saveProvider(provider) {
        this.savingProviderKey = provider.key;
        this.actionMessages[provider.key] = null;

        try {
          await axios.patch(
            this.route('api.v1.investment-provider-configs.update', {
              providerKey: provider.key,
            }),
            this.payloadForProvider(provider),
          );
          toastHelpers.showSuccessToast(__('Provider settings saved.'));
          await this.loadProviders();
        } catch (error) {
          console.error(error);
          this.actionMessages[provider.key] = {
            type: 'error',
            message:
              error.response?.data?.message ||
              __('Unable to save provider settings.'),
          };
          toastHelpers.showErrorToast(__('Unable to save provider settings.'));
        } finally {
          this.savingProviderKey = null;
        }
      },
      async testProvider(provider) {
        this.testingProviderKey = provider.key;
        this.actionMessages[provider.key] = null;

        try {
          const response = await axios.post(
            this.route('api.v1.investment-provider-configs.test', {
              providerKey: provider.key,
            }),
            this.payloadForProvider(provider),
          );
          this.actionMessages[provider.key] = {
            type: 'success',
            message:
              response.data?.message || __('Provider configuration is valid.'),
          };
          toastHelpers.showSuccessToast(
            response.data?.message || __('Provider configuration is valid.'),
          );
        } catch (error) {
          console.error(error);
          this.actionMessages[provider.key] = {
            type: 'error',
            message:
              error.response?.data?.error?.message ||
              __('Provider validation failed.'),
          };
          toastHelpers.showErrorToast(__('Provider validation failed.'));
        } finally {
          this.testingProviderKey = null;
        }
      },
      async removeProvider(provider) {
        if (
          !provider.currentConfig ||
          this.deletingProviderKey === provider.key
        ) {
          return;
        }

        const result = await Swal.fire({
          animation: false,
          text: this.__(
            'Are you sure you want to remove this provider configuration? Saved credentials and custom settings will be deleted.',
          ),
          icon: 'warning',
          showCancelButton: true,
          cancelButtonText: this.__('Cancel'),
          confirmButtonText: this.__('Confirm'),
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary ms-3',
          },
        });

        if (!result.isConfirmed) {
          return;
        }

        this.deletingProviderKey = provider.key;
        this.actionMessages[provider.key] = null;

        try {
          await axios.delete(
            this.route('api.v1.investment-provider-configs.destroy', {
              providerKey: provider.key,
            }),
          );

          toastHelpers.showSuccessToast(__('Provider configuration removed.'));
          await this.loadProviders();
        } catch (error) {
          console.error(error);
          this.actionMessages[provider.key] = {
            type: 'error',
            message:
              error.response?.data?.error?.message ||
              __('Unable to remove provider configuration.'),
          };
          toastHelpers.showErrorToast(
            __('Unable to remove provider configuration.'),
          );
        } finally {
          this.deletingProviderKey = null;
        }
      },
      formatDate(value) {
        return value ? new Date(value).toLocaleString() : __('Never');
      },
      humanize(value) {
        return value
          .replace(/_/g, ' ')
          .replace(/\b\w/g, (character) => character.toUpperCase());
      },
      __,
    },
  };
</script>
