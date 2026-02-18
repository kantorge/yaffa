<template>
  <div class="card" id="aiProviderConfigForm">
    <form
      accept-charset="UTF-8"
      @submit.prevent="onSubmit"
      @keydown="form.onKeydown($event)"
      autocomplete="off"
    >
      <div class="card-header d-flex justify-content-between">
        <div class="card-title">
          {{ __('AI Provider Configuration') }}
        </div>
        <div>
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
      <div class="card-body" v-if="!sandbox_mode">
        <div v-if="!hasConfig && !showForm" class="text-center py-2">
          <p class="mb-3">{{ __('No AI provider configured yet.') }}</p>
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
                  class="form-select"
                  id="provider"
                  name="provider"
                  v-model="form.provider"
                  @change="onProviderChange"
                >
                  <option value="">{{ __('Select provider...') }}</option>
                  <option
                    v-for="(providerData, key) in providers"
                    :key="key"
                    :value="key"
                  >
                    {{ providerData.name }}
                  </option>
                </select>
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="__('Select the AI provider for document processing.')"
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="provider" :form="form" />
            </div>
          </div>

          <div class="row mb-3" v-if="form.provider">
            <label for="model" class="col-form-label col-sm-3">
              {{ __('Model') }}
            </label>
            <div class="col-sm-9">
              <div class="input-group">
                <select
                  class="form-select"
                  id="model"
                  name="model"
                  v-model="form.model"
                  @change="onModelChange"
                >
                  <option value="">{{ __('Select model...') }}</option>
                  <option
                    v-for="model in availableModels"
                    :key="model.name"
                    :value="model.name"
                  >
                    {{ model.name }}
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

          <div class="row mb-3" v-if="modelSupportsVision">
            <label for="vision_enabled" class="col-form-label col-sm-3">
              {{ __('Vision AI') }}
            </label>
            <div class="col-sm-9">
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="vision_enabled"
                  name="vision_enabled"
                  v-model="form.vision_enabled"
                />
                <label class="form-check-label" for="vision_enabled">
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

          <div class="row mb-3" v-if="form.provider">
            <label for="api_key" class="col-form-label col-sm-3">
              {{ __('API Key') }}
            </label>
            <div class="col-sm-9">
              <div class="input-group">
                <input
                  type="password"
                  class="form-control"
                  id="api_key"
                  name="api_key"
                  v-model="form.api_key"
                  :placeholder="
                    hasConfig
                      ? __('Leave blank to keep existing key')
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
                class="form-text text-muted"
                v-if="hasConfig"
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
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="card-body" v-else>
        <div class="alert alert-warning">
          {{
            __(
              'You are in sandbox mode. You cannot change the AI provider settings.',
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
              dusk="button-save-ai-config"
            >
              <i class="fa fa-save"></i>
              {{ hasConfig ? __('Update') : __('Save') }}
            </Button>

            <button
              type="button"
              class="btn btn-secondary me-2"
              @click="testConnection"
              :disabled="!canTest || testingConnection"
              dusk="button-test-connection"
            >
              <i
                :class="[
                  'fa',
                  testingConnection ? 'fa-spinner fa-spin' : 'fa-plug',
                ]"
              ></i>
              {{ __('Test Connection') }}
            </button>

            <button
              v-if="!hasConfig && showForm"
              type="button"
              class="btn btn-outline-secondary"
              @click="cancelAdd"
              dusk="button-cancel-add-ai-provider"
            >
              <i class="fa fa-times"></i>
              {{ __('Cancel') }}
            </button>
          </div>

          <button
            v-if="hasConfig"
            type="button"
            class="btn btn-danger"
            @click="deleteConfig"
            dusk="button-delete-ai-config"
          >
            <i class="fa fa-trash"></i>
            {{ __('Delete Configuration') }}
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
  });
</script>

<script>
  import { __, initializeBootstrapTooltips } from '../helpers';
  import * as toastHelpers from '../toast';
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
      hasConfig: false,
      showForm: false,
      testResult: null,
      testingConnection: false,
      sandbox_mode: window.YAFFA.config.sandbox_mode,
    }),
    computed: {
      availableModels() {
        if (!this.form.provider || !this.providers[this.form.provider]) {
          return [];
        }
        const models = this.providers[this.form.provider].models || [];
        if (Array.isArray(models)) {
          return models.map((model) => ({
            name: model,
            vision: false,
          }));
        }
        return Object.entries(models).map(([name, meta]) => ({
          name,
          vision: Boolean(meta?.vision),
        }));
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
          (this.form.api_key || this.hasConfig)
        );
      },
    },
    mounted() {
      this.loadConfig();

      // Initialize tooltips
      initializeBootstrapTooltips(this.$el);
    },
    methods: {
      loadConfig() {
        // Fetch existing config from API
        axios
          .get('/api/ai/config')
          .then((response) => {
            if (response.data && response.data.id) {
              this.configId = response.data.id;
              this.form.provider = response.data.provider;
              this.form.model = response.data.model;
              this.form.vision_enabled = Boolean(response.data.vision_enabled);
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
          ? `/api/ai/config/${this.configId}`
          : '/api/ai/config';
        const method = this.hasConfig ? 'patch' : 'post';

        // If updating and API key is empty, remove it from the request
        const formData = { ...this.form.data() };
        if (this.hasConfig && !formData.api_key) {
          delete formData.api_key;
        }

        this.form[method](url, formData)
          .then((response) => {
            if (response.status === 200 || response.status === 201) {
              toastHelpers.showSuccessToast(
                this.hasConfig
                  ? __('AI provider configuration updated')
                  : __('AI provider configuration created'),
              );

              this.configId = response.data.id;
              this.hasConfig = true;
              this.showForm = true;
              this.form.api_key = ''; // Clear API key field after save
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
        this.testingConnection = true;
        this.testResult = null;

        const testData = {
          provider: this.form.provider,
          model: this.form.model,
          api_key: this.form.api_key || '__existing__', // Use placeholder if using existing key
        };

        axios
          .post('/api/ai/test', testData)
          .then((response) => {
            this.testResult = {
              success: true,
              message: response.data.message || __('Connection successful'),
            };
          })
          .catch((error) => {
            this.testResult = {
              success: false,
              message:
                error.response?.data?.message || __('Connection test failed'),
            };
          })
          .finally(() => {
            this.testingConnection = false;
          });
      },
      deleteConfig() {
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
              .delete(`/api/ai/config/${this.configId}`)
              .then(() => {
                this.configId = null;
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
