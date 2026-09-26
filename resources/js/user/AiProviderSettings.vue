<template>
    <div id="aiProviderConfigForm" class="card">
        <form
            accept-charset="UTF-8"
            autocomplete="off"
            @submit.prevent="onSubmit"
            @keydown="form.onKeydown($event)"
        >
            <div class="card-header d-flex justify-content-between">
                <div class="card-title">
                    {{ __('AI Provider Configuration') }}
                </div>
                <div>
                    <span
                        v-if="!aiProcessingEnabled"
                        class="fa fa-exclamation-triangle text-warning me-2"
                        :title="
                            __(
                                'These settings can be provided, but will not take effect until AI processing is enabled.',
                            )
                        "
                        data-coreui-toggle="tooltip"
                        data-coreui-placement="top"
                    ></span>
                    <span
                        class="fa fa-info-circle text-info"
                        :title="
                            __(
                                'Configure your AI provider to enable document processing. Your API key is encrypted and stored securely.',
                            )
                        "
                        data-coreui-toggle="tooltip"
                        data-coreui-placement="top"
                    ></span>
                </div>
            </div>
            <div v-if="!sandbox_mode" class="card-body">
                <div v-if="!hasConfig && !showForm" class="text-center py-2">
                    <p class="mb-3">
                        {{ __('No AI provider configured yet.') }}
                    </p>
                    <button
                        type="button"
                        class="btn btn-primary"
                        dusk="button-add-ai-provider"
                        @click="showForm = true"
                    >
                        <i class="fa fa-plus"></i>
                        {{ __('Add AI Provider') }}
                    </button>
                </div>

                <div v-if="hasConfig || showForm">
                    <div class="row mb-3">
                        <label for="provider" class="col-form-label col-sm-3">
                            {{ __('Provider') }}
                        </label>
                        <div class="col-sm-9">
                            <div class="input-group">
                                <select
                                    id="provider"
                                    v-model="form.provider"
                                    class="form-select"
                                    name="provider"
                                    @change="onProviderChange"
                                >
                                    <option value="">
                                        {{ __('Select provider...') }}
                                    </option>
                                    <option
                                        v-for="providerOption in providerOptions"
                                        :key="providerOption.key"
                                        :value="providerOption.key"
                                        :disabled="!providerOption.selectable"
                                    >
                                        {{ providerOption.label }}
                                    </option>
                                </select>
                                <span
                                    class="input-group-text btn btn-outline-input-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="top"
                                    :title="
                                        __(
                                            'Select the AI provider for document processing.',
                                        )
                                    "
                                >
                                    <i class="fa fa-info-circle"></i>
                                </span>
                            </div>
                            <HasError field="provider" :form="form" />
                        </div>
                    </div>

                    <div v-if="form.provider" class="row mb-3">
                        <label for="model" class="col-form-label col-sm-3">
                            {{ __('Model') }}
                        </label>
                        <div class="col-sm-9">
                            <div class="input-group">
                                <select
                                    id="model"
                                    v-model="form.model"
                                    class="form-select"
                                    name="model"
                                    @change="onModelChange"
                                >
                                    <option value="">
                                        {{ __('Select model...') }}
                                    </option>
                                    <option
                                        v-for="model in availableModels"
                                        :key="model.name"
                                        :value="model.name"
                                        :disabled="!model.selectable"
                                    >
                                        {{ model.label }}
                                    </option>
                                </select>
                                <span
                                    class="input-group-text btn btn-outline-input-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="top"
                                    :title="
                                        __(
                                            'Select the AI model to use for processing. Different models have different capabilities and costs.',
                                        )
                                    "
                                >
                                    <i class="fa fa-info-circle"></i>
                                </span>
                            </div>
                            <HasError field="model" :form="form" />
                        </div>
                    </div>

                    <div v-if="showLegacySelectionNotice" class="row mb-3">
                        <div class="col-sm-9 offset-sm-3">
                            <div class="alert alert-warning mb-0" role="alert">
                                <i class="fa fa-exclamation-triangle me-2"></i>
                                {{ legacySelectionNoticeText }}
                            </div>
                        </div>
                    </div>

                    <div v-if="modelSupportsVision" class="row mb-3">
                        <label
                            for="vision_enabled"
                            class="col-form-label col-sm-3"
                        >
                            {{ __('Vision AI') }}
                        </label>
                        <div class="col-sm-9">
                            <div class="form-check">
                                <input
                                    id="vision_enabled"
                                    v-model="form.vision_enabled"
                                    class="form-check-input"
                                    type="checkbox"
                                    name="vision_enabled"
                                />
                                <label
                                    class="form-check-label"
                                    for="vision_enabled"
                                >
                                    {{ __('Enable Vision AI for images') }}
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                {{
                                    __(
                                        'Uses the selected model to process image-based documents. Additional AI costs apply.',
                                    )
                                }}
                            </small>
                        </div>
                    </div>

                    <div v-if="form.provider" class="row mb-3">
                        <label for="api_key" class="col-form-label col-sm-3">
                            {{ __('API Key') }}
                        </label>
                        <div class="col-sm-9">
                            <div class="input-group">
                                <input
                                    id="api_key"
                                    v-model="form.api_key"
                                    type="password"
                                    class="form-control"
                                    name="api_key"
                                    :placeholder="
                                        hasConfig && !providerChanged
                                            ? __(
                                                  'Leave blank to keep existing key',
                                              )
                                            : __('Enter your API key')
                                    "
                                />
                                <span
                                    class="input-group-text btn btn-outline-input-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="top"
                                    :title="
                                        __(
                                            'Your API key from the provider. This will be encrypted before storage.',
                                        )
                                    "
                                >
                                    <i class="fa fa-info-circle"></i>
                                </span>
                            </div>
                            <HasError field="api_key" :form="form" />
                            <small
                                v-if="hasConfig && !providerChanged"
                                class="form-text text-muted"
                                dusk="api-key-hint"
                            >
                                {{
                                    __(
                                        'Current key is hidden. Enter a new key only if you want to change it.',
                                    )
                                }}
                            </small>
                        </div>
                    </div>

                    <div v-if="testResult" class="row mb-3">
                        <div class="col-sm-9 offset-sm-3">
                            <div
                                :class="[
                                    'alert',
                                    testResult.success
                                        ? 'alert-success'
                                        : 'alert-danger',
                                ]"
                                role="alert"
                            >
                                <i
                                    :class="[
                                        'fa',
                                        testResult.success
                                            ? 'fa-check-circle'
                                            : 'fa-times-circle',
                                    ]"
                                ></i>
                                {{ testResult.message }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="card-body">
                <div class="alert alert-warning">
                    {{
                        __(
                            'You are in sandbox mode. You cannot change the AI provider settings.',
                        )
                    }}
                </div>
            </div>

            <div
                v-if="!sandbox_mode && (hasConfig || showForm)"
                class="card-footer"
            >
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <Button
                            class="btn btn-primary me-2"
                            :form="form"
                            dusk="button-save-ai-config"
                        >
                            <i v-show="!form.busy" class="fa me-1 fa-save"></i
                            >{{ hasConfig ? __('Update') : __('Save') }}
                        </Button>

                        <button
                            type="button"
                            class="btn btn-secondary me-2"
                            :disabled="!canTest || testingConnection"
                            dusk="button-test-connection"
                            @click="testConnection"
                        >
                            <i
                                :class="[
                                    'fa',
                                    'me-1',
                                    testingConnection
                                        ? 'fa-spinner fa-spin'
                                        : 'fa-plug',
                                ]"
                            ></i
                            >{{ __('Test Connection') }}
                        </button>

                        <button
                            v-if="!hasConfig && showForm"
                            type="button"
                            class="btn btn-secondary"
                            dusk="button-cancel-add-ai-provider"
                            @click="cancelAdd"
                        >
                            <i class="fa me-1 fa-times"></i>{{ __('Cancel') }}
                        </button>
                    </div>

                    <button
                        v-if="hasConfig"
                        type="button"
                        class="btn btn-danger"
                        dusk="button-delete-ai-config"
                        @click="deleteConfig"
                    >
                        <i class="fa me-1 fa-trash"></i
                        >{{ __('Delete Configuration') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
    const props = defineProps({
        providers: {
            type: Object,
            default: () => window.aiProviders || {},
        },
        aiProcessingEnabled: {
            type: Boolean,
            default: true,
        },
    });
</script>

<script>
    import { __ } from '@/shared/lib/i18n';
    import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
    import * as toastHelpers from '@/shared/lib/toast';
    import Form from 'vform';
    import { Button, HasError } from 'vform/src/components/bootstrap5';
    import Swal from 'sweetalert2';

    export default {
        name: 'AiProviderSettings',
        components: {
            Button,
            HasError,
        },
        data: () => ({
            form: new Form({
                provider: '',
                model: '',
                api_key: '',
                vision_enabled: false,
            }),
            configId: null,
            originalProvider: null,
            hasConfig: false,
            showForm: false,
            testResult: null,
            testingConnection: false,
            sandbox_mode: window.YAFFA.config.sandbox_mode,
        }),
        computed: {
            providerOptions() {
                return Object.entries(this.providers || {}).map(
                    ([key, providerData]) => {
                        const supported =
                            this.isProviderSupported(providerData);
                        const selectable =
                            supported ||
                            (this.hasConfig && this.form.provider === key);

                        return {
                            key,
                            supported,
                            selectable,
                            label: supported
                                ? providerData.name
                                : `${providerData.name} (${__('Deprecated')})`,
                        };
                    },
                );
            },
            availableModels() {
                if (
                    !this.form.provider ||
                    !this.providers[this.form.provider]
                ) {
                    return [];
                }

                const isCurrentProvider = this.hasConfig;
                const models = this.providers[this.form.provider].models || [];

                if (Array.isArray(models)) {
                    return models.map((model) => ({
                        name: model,
                        vision: false,
                        supported: true,
                        selectable: true,
                        label: model,
                    }));
                }

                return Object.entries(models).map(([name, meta]) => ({
                    name,
                    vision: Boolean(meta?.vision),
                    supported: this.isModelSupported(meta),
                    selectable:
                        this.isModelSupported(meta) ||
                        (isCurrentProvider && this.form.model === name),
                    label: this.isModelSupported(meta)
                        ? name
                        : `${name} (${__('Deprecated')})`,
                }));
            },
            selectedProviderSupported() {
                if (!this.form.provider) {
                    return true;
                }

                return this.isProviderSupported(
                    this.providers[this.form.provider],
                );
            },
            selectedModel() {
                if (!this.form.model) {
                    return null;
                }

                return (
                    this.availableModels.find(
                        (model) => model.name === this.form.model,
                    ) || null
                );
            },
            selectedModelSupported() {
                if (!this.form.model) {
                    return true;
                }

                return Boolean(this.selectedModel?.supported);
            },
            showLegacySelectionNotice() {
                if (!this.hasConfig) {
                    return false;
                }

                return (
                    !this.selectedProviderSupported ||
                    (this.form.model && !this.selectedModelSupported)
                );
            },
            legacySelectionNoticeText() {
                if (!this.selectedProviderSupported) {
                    return __(
                        'Your current provider is deprecated. You can keep it as-is, but once you change it, you cannot select it again.',
                    );
                }

                return __(
                    'Your current model is deprecated. You can keep it as-is, but once you change it, you cannot select it again.',
                );
            },
            modelSupportsVision() {
                if (!this.form.provider || !this.form.model) {
                    return false;
                }
                const models = this.providers[this.form.provider]?.models || {};
                if (Array.isArray(models)) {
                    return false;
                }
                return Boolean(models[this.form.model]?.vision);
            },
            canTest() {
                return (
                    this.form.provider &&
                    this.form.model &&
                    (this.form.api_key ||
                        (this.hasConfig && !this.providerChanged))
                );
            },
            // A different provider cannot be expected to accept the key stored for the
            // previous one, so once the provider selection changes, a fresh key is required
            // rather than silently reusing the existing one.
            providerChanged() {
                return (
                    this.hasConfig &&
                    this.originalProvider !== null &&
                    this.form.provider !== this.originalProvider
                );
            },
        },
        mounted() {
            this.loadConfig();
            initializeBootstrapTooltips(this.$el);
        },
        updated() {
            this.$nextTick(() => {
                initializeBootstrapTooltips(this.$el);
            });
        },
        methods: {
            isProviderSupported(providerData) {
                if (!providerData || typeof providerData !== 'object') {
                    return false;
                }

                return providerData.supported !== false;
            },
            isModelSupported(modelMeta) {
                if (!modelMeta || typeof modelMeta !== 'object') {
                    return true;
                }

                return modelMeta.supported !== false;
            },
            loadConfig() {
                // Fetch existing config from API
                axios
                    .get(this.route('api.v1.ai.config.show'))
                    .then((response) => {
                        if (response.data && response.data.id) {
                            this.configId = response.data.id;
                            this.originalProvider = response.data.provider;
                            this.form.provider = response.data.provider;
                            this.form.model = response.data.model;
                            this.form.vision_enabled = Boolean(
                                response.data.vision_enabled,
                            );
                            this.form.api_key = ''; // Don't populate API key for security
                            this.hasConfig = true;
                            this.showForm = true;
                        }
                    })
                    .catch((error) => {
                        if (error.response && error.response.status === 404) {
                            // No config yet, that's fine
                            this.hasConfig = false;
                            this.showForm = false;
                        } else {
                            console.error('Failed to load AI config:', error);
                        }
                    });
            },
            onProviderChange() {
                // Reset model when provider changes
                this.form.model = '';
                this.form.vision_enabled = false;
                this.testResult = null;
            },
            onModelChange() {
                if (!this.modelSupportsVision) {
                    this.form.vision_enabled = false;
                }
                this.testResult = null;
            },
            onSubmit() {
                let _vue = this;
                this.form.busy = true;
                this.testResult = null;

                const url = this.hasConfig
                    ? this.route('api.v1.ai.config.update', {
                          aiProviderConfig: this.configId,
                      })
                    : this.route('api.v1.ai.config.store');
                const method = this.hasConfig ? 'patch' : 'post';

                // If updating and API key is empty, remove it from the request
                const formData = { ...this.form.data() };
                if (this.hasConfig && !formData.api_key) {
                    delete formData.api_key;
                }

                this.form[method](url, formData)
                    .then((response) => {
                        if (
                            response.status === 200 ||
                            response.status === 201
                        ) {
                            toastHelpers.showSuccessToast(
                                this.hasConfig
                                    ? __('AI provider configuration updated')
                                    : __('AI provider configuration created'),
                            );

                            this.configId = response.data.id;
                            this.originalProvider = this.form.provider;
                            this.hasConfig = true;
                            this.showForm = true;
                            this.form.api_key = ''; // Clear API key field after save
                        }
                    })
                    .catch((error) => {
                        if (error.response && error.response.status === 422) {
                            toastHelpers.showErrorToast(
                                __(
                                    'Validation failed. Please check the form for errors.',
                                ),
                            );
                        } else {
                            console.error(error);
                            toastHelpers.showErrorToast(
                                __(
                                    'An error occurred. Please try again later.',
                                ),
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

                // A new provider cannot be expected to accept the key stored for the previous
                // one, so only fall back to the existing key when the provider hasn't changed.
                const useExistingKey = this.hasConfig && !this.providerChanged;

                const testData = {
                    provider: this.form.provider,
                    model: this.form.model,
                    api_key:
                        this.form.api_key ||
                        (useExistingKey ? '__existing__' : ''),
                };

                axios
                    .post(this.route('api.v1.ai.config.test'), testData)
                    .then((response) => {
                        this.testResult = {
                            success: true,
                            message:
                                response.data.message ||
                                __('Connection successful'),
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
            deleteConfig() {
                Swal.fire({
                    animation: false,
                    text: this.__(
                        'Are you sure you want to delete this configuration?',
                    ),
                    icon: 'warning',
                    showCancelButton: true,
                    cancelButtonText: this.__('Cancel'),
                    confirmButtonText: this.__('Confirm'),
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-secondary ms-3',
                    },
                }).then((result) => {
                    if (result.isConfirmed) {
                        axios
                            .delete(
                                this.route('api.v1.ai.config.destroy', {
                                    aiProviderConfig: this.configId,
                                }),
                            )
                            .then(() => {
                                this.configId = null;
                                this.originalProvider = null;
                                this.hasConfig = false;
                                this.showForm = false;
                                this.form.reset();
                                this.testResult = null;

                                toastHelpers.showSuccessToast(
                                    __('AI provider configuration deleted'),
                                );
                            })
                            .catch((error) => {
                                console.error(error);
                                toastHelpers.showErrorToast(
                                    __(
                                        'Failed to delete configuration. Please try again.',
                                    ),
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
