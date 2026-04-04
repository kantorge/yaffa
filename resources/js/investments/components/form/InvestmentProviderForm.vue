<template>
  <div>
    <div class="row mb-3">
      <label for="investment_price_provider" class="col-form-label col-sm-3">
        {{ __('Price provider') }}
      </label>
      <div class="col-sm-9">
        <div class="input-group">
          <select
            id="investment_price_provider"
            v-model="selectedProvider"
            name="investment_price_provider"
            class="form-select"
            @change="ensureSelectedProviderState"
          >
            <option value="">{{ __(' < No price provider > ') }}</option>
            <option
              v-for="provider in providerList"
              :key="provider.key"
              :value="provider.key"
            >
              {{ optionLabel(provider) }}
            </option>
          </select>
          <span
            v-if="selectedProvider"
            :key="selectedProviderStatusTooltip"
            class="input-group-text"
            data-coreui-toggle="tooltip"
            data-coreui-placement="top"
            :title="selectedProviderStatusTooltip"
          >
            <i class="fa" :class="selectedProviderStatusIconClass"></i>
          </span>
        </div>
      </div>
    </div>

    <div v-if="selectedProvider" class="row mb-3">
      <div class="col-sm-3"></div>
      <div class="col-sm-9">
        <div class="border rounded p-3 bg-light">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-semibold">{{ __('Provider settings') }}</div>
            <div>
              <button
                class="btn btn-sm btn-outline-secondary"
                type="button"
                :disabled="testFetchInProgress"
                @click="testProviderFetch"
              >
                <i
                  :class="[
                    'fa',
                    testFetchInProgress ? 'fa-spinner fa-spin' : 'fa-plug',
                  ]"
                ></i>
                {{ __('Test fetch') }}
              </button>
            </div>
          </div>

          <p v-if="selectedSettingFields.length === 0" class="text-muted mb-0">
            {{ providerSettingsInfoMessage }}
          </p>

          <div v-else class="row g-3">
            <div
              v-for="field in selectedSettingFields"
              :key="field.key"
              class="col-12"
            >
              <label :for="fieldId(field.key)" class="form-label">
                {{ field.schema.label || humanizeField(field.key) }}
              </label>
              <input
                :id="fieldId(field.key)"
                v-model="selectedProviderSettings[field.key]"
                :type="fieldInputType(field.schema)"
                class="form-control"
                :required="isRequiredField(field.key)"
              />
              <div v-if="field.schema.helpText" class="form-text">
                {{ field.schema.helpText }}
              </div>
            </div>
          </div>

          <div
            v-if="testFetchResult.message"
            class="alert mt-3 mb-0"
            :class="
              testFetchResult.type === 'success'
                ? 'alert-success'
                : 'alert-danger'
            "
            role="alert"
          >
            {{ testFetchResult.message }}
          </div>
        </div>
      </div>
    </div>

    <template
      v-for="field in selectedSettingFields"
      :key="`provider-setting-${field.key}`"
    >
      <input
        :name="`provider_settings[${field.key}]`"
        :value="selectedProviderSettings[field.key] || ''"
        type="hidden"
      />
    </template>
  </div>
</template>

<script>
  import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
  import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'InvestmentProviderForm',
    props: {
      providerOptions: {
        type: Object,
        required: true,
      },
      currentInvestment: {
        type: Object,
        default: null,
      },
      oldInput: {
        type: Object,
        default: () => ({}),
      },
      symbolFieldId: {
        type: String,
        required: true,
      },
    },
    setup(props) {
      const selectedProvider = ref(
        props.oldInput?.investment_price_provider ??
          props.currentInvestment?.investment_price_provider ??
          '',
      );
      const providerAvailability = ref([]);
      const settingsByProvider = reactive({});
      const testFetchInProgress = ref(false);
      const testFetchResult = reactive({
        type: 'success',
        message: '',
      });

      const initializeSettingsForProvider = (providerKey) => {
        if (!providerKey || settingsByProvider[providerKey]) {
          return;
        }

        const schema =
          props.providerOptions?.[providerKey]?.investmentSettingsSchema;
        const properties = schema?.properties || {};
        const baseSettings = {};

        Object.keys(properties).forEach((fieldKey) => {
          baseSettings[fieldKey] = '';
        });

        const oldProviderSettings = props.oldInput?.provider_settings || {};
        const currentProviderSettings =
          props.currentInvestment?.provider_settings || {};
        const mergedSettings = {
          ...baseSettings,
          ...(selectedProvider.value === providerKey
            ? currentProviderSettings
            : {}),
          ...(selectedProvider.value === providerKey
            ? oldProviderSettings
            : {}),
        };

        settingsByProvider[providerKey] = mergedSettings;
      };

      const providerAvailabilityMap = computed(() => {
        return providerAvailability.value.reduce((carry, provider) => {
          carry[provider.key] = provider;
          return carry;
        }, {});
      });

      const providerList = computed(() =>
        Object.entries(props.providerOptions || {}).map(([key, metadata]) => ({
          key,
          ...metadata,
          ...(providerAvailabilityMap.value[key] || {}),
        })),
      );

      const providerStatus = (provider) => {
        if (!provider) {
          return {
            usable: true,
            label: __('Not configured'),
            description: '',
            badgeClass: 'text-bg-secondary',
          };
        }

        if (Array.isArray(provider.reasonFlags)) {
          return {
            usable: Boolean(provider.available),
            label: provider.statusLabel || __('Not configured'),
            description: provider.statusDescription || '',
            badgeClass: badgeClassForReasonFlags(provider.reasonFlags),
          };
        }

        const requiredFields = provider.userSettingsSchema?.required || [];

        if (!requiredFields.length) {
          return {
            usable: true,
            label: __('Available'),
            description: '',
            badgeClass: 'text-bg-success',
          };
        }

        return {
          usable: false,
          label: __('Setup required'),
          description: '',
          badgeClass: 'text-bg-warning',
        };
      };

      const badgeClassForReasonFlags = (reasonFlags) => {
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
      };

      const selectedProviderMetadata = computed(() => {
        if (!selectedProvider.value) {
          return null;
        }

        return (
          providerList.value.find(
            (provider) => provider.key === selectedProvider.value,
          ) || null
        );
      });

      const selectedSettingFields = computed(() => {
        const properties =
          selectedProviderMetadata.value?.investmentSettingsSchema
            ?.properties || {};

        return Object.entries(properties).map(([key, schema]) => ({
          key,
          schema,
        }));
      });

      const selectedProviderSettings = computed(() => {
        if (!selectedProvider.value) {
          return {};
        }

        initializeSettingsForProvider(selectedProvider.value);

        return settingsByProvider[selectedProvider.value];
      });
      const selectedProviderState = computed(() => {
        return providerStatus(selectedProviderMetadata.value);
      });

      const selectedProviderStatusLabel = computed(
        () => selectedProviderState.value.label,
      );
      const selectedProviderDescription = computed(
        () => selectedProviderState.value.description,
      );
      const selectedProviderBadgeClass = computed(
        () => selectedProviderState.value.badgeClass,
      );
      const selectedProviderStatusIconClass = computed(() => {
        if (!selectedProvider.value) {
          return 'fa-circle-info text-info';
        }

        if (selectedProviderBadgeClass.value === 'text-bg-warning') {
          return 'fa-exclamation-triangle text-warning';
        }

        if (selectedProviderBadgeClass.value === 'text-bg-secondary') {
          return 'fa-circle-minus text-secondary';
        }

        return 'fa-circle-check text-success';
      });
      const selectedProviderStatusTooltip = computed(() => {
        const statusLabel = selectedProviderStatusLabel.value;
        const statusDescription = selectedProviderDescription.value;

        if (!statusDescription) {
          return statusLabel;
        }

        return `${statusLabel}: ${statusDescription}`;
      });

      const providerSettingsInfoMessage = computed(() => {
        if (!selectedProviderMetadata.value) {
          return '';
        }

        return __(
          'This provider does not require extra investment-specific settings. Make sure the Symbol field is set to a symbol supported by this provider.',
        );
      });

      const ensureSelectedProviderState = () => {
        initializeSettingsForProvider(selectedProvider.value);
        testFetchResult.message = '';
      };

      const loadProviderAvailability = () => {
        return axios
          .get(
            `${window.route('api.v1.investment-price-providers.available')}?include_unavailable=1`,
          )
          .then((response) => {
            providerAvailability.value = Array.isArray(response.data)
              ? response.data
              : [];
          })
          .catch(() => {});
      };

      const optionLabel = (provider) => {
        const status = providerStatus(provider);

        if (status.usable) {
          return provider.displayName;
        }

        return `${provider.displayName} (${status.label})`;
      };

      const fieldId = (fieldKey) => `provider-setting-${fieldKey}`;
      const fieldInputType = (schema) =>
        schema?.format === 'url' ? 'url' : 'text';
      const isRequiredField = (fieldKey) => {
        const required =
          selectedProviderMetadata.value?.investmentSettingsSchema?.required ||
          [];
        return required.includes(fieldKey);
      };
      const humanizeField = (fieldKey) =>
        fieldKey
          .replace(/_/g, ' ')
          .replace(/\b\w/g, (char) => char.toUpperCase());

      const getSymbolValue = () => {
        const symbolInput = window.document.getElementById(props.symbolFieldId);

        return symbolInput?.value?.trim() || '';
      };

      const testProviderFetch = async () => {
        if (!selectedProvider.value || testFetchInProgress.value) {
          return;
        }

        const symbol = getSymbolValue();

        if (symbol === '') {
          const message = __(
            'Please set the Symbol field before testing this provider.',
          );
          testFetchResult.type = 'error';
          testFetchResult.message = message;
          toastHelpers.showErrorToast(message);
          return;
        }

        testFetchInProgress.value = true;
        testFetchResult.message = '';

        try {
          const response = await axios.post(
            window.route('api.v1.investment-price-providers.test-fetch', {
              providerKey: selectedProvider.value,
            }),
            {
              symbol,
              provider_settings: selectedProviderSettings.value,
            },
          );

          const resultMessage = __('Test fetch successful: :price (:date)', {
            price: response.data?.price,
            date: response.data?.date,
          });
          testFetchResult.type = 'success';
          testFetchResult.message = resultMessage;
          toastHelpers.showSuccessToast(resultMessage);
        } catch (error) {
          const message =
            error.response?.data?.error?.message ||
            error.response?.data?.message ||
            __('Test fetch failed.');
          testFetchResult.type = 'error';
          testFetchResult.message = message;
          toastHelpers.showErrorToast(message);
        } finally {
          testFetchInProgress.value = false;
        }
      };

      onMounted(() => {
        initializeSettingsForProvider(selectedProvider.value);
        initializeBootstrapTooltips();
        loadProviderAvailability();
      });

      watch(selectedProviderStatusTooltip, async () => {
        await nextTick();
        initializeBootstrapTooltips();
      });

      return {
        fieldId,
        fieldInputType,
        humanizeField,
        isRequiredField,
        optionLabel,
        providerList,
        providerSettingsInfoMessage,
        selectedProvider,
        selectedProviderStatusIconClass,
        selectedProviderStatusTooltip,
        selectedProviderSettings,
        selectedSettingFields,
        ensureSelectedProviderState,
        testFetchInProgress,
        testFetchResult,
        testProviderFetch,
      };
    },
  };
</script>
