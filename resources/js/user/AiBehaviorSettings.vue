<template>
  <div class="card" id="aiBehaviorSettingsForm">
    <form
      accept-charset="UTF-8"
      @submit.prevent="onSubmit"
      @keydown="form.onKeydown($event)"
      autocomplete="off"
    >
      <div class="card-header d-flex justify-content-between">
        <div class="card-title">
          {{ __('AI Behavior Settings') }}
        </div>
        <div>
          <span
            class="fa fa-info-circle text-info"
            :title="
              __(
                'Configure AI document processing behavior, thresholds, and OCR settings.',
              )
            "
            data-coreui-toggle="tooltip"
            data-coreui-placement="top"
          ></span>
        </div>
      </div>
      <div class="card-body" v-if="!sandbox_mode">
        <div v-if="loading" class="text-center py-3">
          <i class="fa fa-spinner fa-spin"></i>
          {{ __('Loading settings...') }}
        </div>

        <template v-else>
          <!-- General -->
          <h6 class="text-muted mb-3">{{ __('General') }}</h6>

          <div class="row mb-3">
            <label class="col-form-label col-sm-4">
              {{ __('AI Processing') }}
            </label>
            <div class="col-sm-8">
              <div class="form-check form-switch">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="ai_enabled"
                  v-model="form.ai_enabled"
                />
                <label class="form-check-label" for="ai_enabled">
                  {{ __('Enable AI document processing') }}
                </label>
              </div>
              <small class="form-text text-muted">
                {{
                  __(
                    'When disabled, no new documents will be created or processed by AI.',
                  )
                }}
              </small>
            </div>
          </div>

          <div class="row mb-3">
            <label class="col-form-label col-sm-4">
              {{ __('Prompt Context') }}
            </label>
            <div class="col-sm-8">
              <div class="form-check form-switch">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="prompt_chat_history_enabled"
                  v-model="form.prompt_chat_history_enabled"
                />
                <label
                  class="form-check-label"
                  for="prompt_chat_history_enabled"
                >
                  {{ __('Use previous document prompt/response history') }}
                </label>
              </div>
              <small class="form-text text-muted">
                {{
                  __(
                    'When enabled, each AI step uses prior prompt/response pairs from this document as conversation context. This can improve accuracy for complex documents but increases processing time and token usage.',
                  )
                }}
              </small>
            </div>
          </div>

          <hr class="my-3" />

          <!-- Category matching -->
          <h6 class="text-muted mb-3">{{ __('Category Matching') }}</h6>

          <div
            v-if="categoryWarning"
            class="alert alert-warning mb-3"
            role="alert"
          >
            <i class="fa fa-exclamation-triangle"></i>
            {{ categoryWarning }}
          </div>

          <div class="row mb-3">
            <label for="category_matching_mode" class="col-form-label col-sm-4">
              {{ __('Matching Mode') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <select
                  class="form-select"
                  id="category_matching_mode"
                  name="category_matching_mode"
                  v-model="form.category_matching_mode"
                >
                  <option value="best_match">{{ __('Best match') }}</option>
                  <option value="parent_only">{{ __('Parent only') }}</option>
                  <option value="parent_preferred">
                    {{ __('Parent preferred') }}
                  </option>
                  <option value="child_preferred">
                    {{ __('Child preferred') }}
                  </option>
                  <option value="child_only">{{ __('Child only') }}</option>
                </select>
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Controls whether AI prioritizes best semantic match, parent categories, or child categories when assigning document items.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="category_matching_mode" :form="form" />
            </div>
          </div>

          <hr class="my-3" />

          <!-- Asset matching -->
          <h6 class="text-muted mb-3">{{ __('Asset Matching') }}</h6>

          <div class="row mb-3">
            <label
              for="asset_similarity_threshold"
              class="col-form-label col-sm-4"
            >
              {{ __('Similarity Threshold') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="asset_similarity_threshold"
                  name="asset_similarity_threshold"
                  v-model.number="form.asset_similarity_threshold"
                  step="0.01"
                  min="0"
                  max="1"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Minimum similarity score (0–1) to suggest an asset match.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="asset_similarity_threshold" :form="form" />
            </div>
          </div>

          <div class="row mb-3">
            <label for="asset_max_suggestions" class="col-form-label col-sm-4">
              {{ __('Max Suggestions') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="asset_max_suggestions"
                  name="asset_max_suggestions"
                  v-model.number="form.asset_max_suggestions"
                  min="1"
                  max="255"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Maximum number of asset match suggestions to return per document.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="asset_max_suggestions" :form="form" />
            </div>
          </div>

          <div class="row mb-3">
            <label
              for="match_auto_accept_threshold"
              class="col-form-label col-sm-4"
            >
              {{ __('Auto-accept Threshold') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="match_auto_accept_threshold"
                  name="match_auto_accept_threshold"
                  v-model.number="form.match_auto_accept_threshold"
                  step="0.01"
                  min="0"
                  max="1"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Similarity score above which a match is automatically accepted without review.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="match_auto_accept_threshold" :form="form" />
            </div>
          </div>

          <hr class="my-3" />

          <!-- Duplicate detection -->
          <h6 class="text-muted mb-3">
            {{ __('Duplicate Transaction Detection') }}
          </h6>

          <div class="row mb-3">
            <label
              for="duplicate_date_window_days"
              class="col-form-label col-sm-4"
            >
              {{ __('Date Window (days)') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="duplicate_date_window_days"
                  name="duplicate_date_window_days"
                  v-model.number="form.duplicate_date_window_days"
                  min="1"
                  max="255"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Number of days within which two documents with similar amounts are considered potential duplicates.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="duplicate_date_window_days" :form="form" />
            </div>
          </div>

          <div class="row mb-3">
            <label
              for="duplicate_amount_tolerance_percent"
              class="col-form-label col-sm-4"
            >
              {{ __('Amount Tolerance (%)') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="duplicate_amount_tolerance_percent"
                  name="duplicate_amount_tolerance_percent"
                  v-model.number="form.duplicate_amount_tolerance_percent"
                  step="0.01"
                  min="0"
                  max="100"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Percentage tolerance for amount comparison when detecting duplicates.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError
                field="duplicate_amount_tolerance_percent"
                :form="form"
              />
            </div>
          </div>

          <div class="row mb-3">
            <label
              for="duplicate_similarity_threshold"
              class="col-form-label col-sm-4"
            >
              {{ __('Similarity Threshold') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="duplicate_similarity_threshold"
                  name="duplicate_similarity_threshold"
                  v-model.number="form.duplicate_similarity_threshold"
                  step="0.01"
                  min="0"
                  max="1"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Minimum similarity score (0–1) to flag two documents as duplicates.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="duplicate_similarity_threshold" :form="form" />
            </div>
          </div>

          <hr class="my-3" />

          <!-- OCR & Vision -->
          <h6 class="text-muted mb-3">{{ __('OCR & Image Processing') }}</h6>

          <div v-if="!tesseractAvailable" class="alert alert-info mb-3">
            <i class="fa fa-circle-info me-1"></i>
            {{
              __(
                'Tesseract OCR is not available in this environment, so Tesseract-specific settings are hidden.',
              )
            }}
          </div>

          <div class="row mb-3" v-if="tesseractAvailable">
            <label for="ocr_language" class="col-form-label col-sm-4">
              {{ __('OCR Language') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="text"
                  class="form-control"
                  id="ocr_language"
                  name="ocr_language"
                  v-model="form.ocr_language"
                  maxlength="64"
                  placeholder="eng"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Tesseract OCR language code (e.g. eng, fra, deu). Use + to combine: eng+fra.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="ocr_language" :form="form" />
            </div>
          </div>

          <div class="row mb-3">
            <label for="image_max_width_vision" class="col-form-label col-sm-4">
              {{ __('Vision Max Width (px)') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="image_max_width_vision"
                  name="image_max_width_vision"
                  v-model="form.image_max_width_vision"
                  min="1"
                  max="65535"
                  :placeholder="__('No limit')"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Maximum image width in pixels for Vision AI processing. Images wider than this will be downscaled.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <small class="form-text text-warning">
                <i class="fa fa-exclamation-triangle me-1"></i>
                {{
                  __(
                    'Optional. Leaving this empty disables Vision width downscaling and can increase token usage.',
                  )
                }}
              </small>
              <HasError field="image_max_width_vision" :form="form" />
            </div>
          </div>

          <div class="row mb-3">
            <label
              for="image_max_height_vision"
              class="col-form-label col-sm-4"
            >
              {{ __('Vision Max Height (px)') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="image_max_height_vision"
                  name="image_max_height_vision"
                  v-model="form.image_max_height_vision"
                  min="1"
                  max="65535"
                  :placeholder="__('No limit')"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Maximum image height in pixels for Vision AI processing. Images taller than this will be downscaled.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <small class="form-text text-warning">
                <i class="fa fa-exclamation-triangle me-1"></i>
                {{
                  __(
                    'Optional. Leaving this empty disables Vision height downscaling and can increase token usage.',
                  )
                }}
              </small>
              <HasError field="image_max_height_vision" :form="form" />
            </div>
          </div>

          <div class="row mb-3">
            <label for="image_quality_vision" class="col-form-label col-sm-4">
              {{ __('Vision Image Quality') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="image_quality_vision"
                  name="image_quality_vision"
                  v-model.number="form.image_quality_vision"
                  min="1"
                  max="100"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'JPEG quality (1–100) for images sent to Vision AI. Lower values reduce API costs but may impact accuracy.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="image_quality_vision" :form="form" />
            </div>
          </div>

          <div class="row mb-3" v-if="tesseractAvailable">
            <label
              for="image_max_width_tesseract"
              class="col-form-label col-sm-4"
            >
              {{ __('Tesseract Max Width (px)') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="image_max_width_tesseract"
                  name="image_max_width_tesseract"
                  v-model="form.image_max_width_tesseract"
                  min="1"
                  max="65535"
                  :placeholder="__('No limit')"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Optional maximum image width in pixels for Tesseract OCR. Leave blank for no limit.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="image_max_width_tesseract" :form="form" />
            </div>
          </div>

          <div class="row mb-0" v-if="tesseractAvailable">
            <label
              for="image_max_height_tesseract"
              class="col-form-label col-sm-4"
            >
              {{ __('Tesseract Max Height (px)') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="image_max_height_tesseract"
                  name="image_max_height_tesseract"
                  v-model="form.image_max_height_tesseract"
                  min="1"
                  max="65535"
                  :placeholder="__('No limit')"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Optional maximum image height in pixels for Tesseract OCR. Leave blank for no limit.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="image_max_height_tesseract" :form="form" />
            </div>
          </div>
        </template>
      </div>
      <div class="card-body" v-else>
        <div class="alert alert-warning">
          {{
            __('You are in sandbox mode. You cannot change the AI settings.')
          }}
        </div>
      </div>

      <div class="card-footer" v-if="!loading">
        <Button
          class="btn btn-primary"
          :form="form"
          dusk="button-save-ai-behavior-settings"
        >
          <i class="fa fa-save me-1" v-show="!form.busy"></i>
          {{ __('Save') }}
        </Button>
      </div>
    </form>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import Form from 'vform';
  import { Button, HasError } from 'vform/src/components/bootstrap5';

  export default {
    name: 'AiBehaviorSettings',
    props: {
      tesseractAvailable: {
        type: Boolean,
        default: true,
      },
    },
    emits: ['ai-processing-changed'],
    components: {
      Button,
      HasError,
    },
    data: () => ({
      form: new Form({
        ai_enabled: false,
        prompt_chat_history_enabled: true,
        ocr_language: '',
        image_max_width_vision: null,
        image_max_height_vision: null,
        image_quality_vision: null,
        image_max_width_tesseract: null,
        image_max_height_tesseract: null,
        asset_similarity_threshold: null,
        asset_max_suggestions: null,
        match_auto_accept_threshold: null,
        duplicate_date_window_days: null,
        duplicate_amount_tolerance_percent: null,
        duplicate_similarity_threshold: null,
        category_matching_mode: 'child_preferred',
      }),
      loading: true,
      warnings: [],
      sandbox_mode: window.YAFFA.config.sandbox_mode,
    }),
    computed: {
      categoryWarning() {
        return this.warnings.length > 0 ? this.warnings[0] : null;
      },
    },
    mounted() {
      this.loadSettings();
      initializeBootstrapTooltips(this.$el);
    },
    updated() {
      this.$nextTick(() => {
        initializeBootstrapTooltips(this.$el);
      });
    },
    methods: {
      loadSettings() {
        axios
          .get(this.route('api.v1.ai.settings.show'))
          .then((response) => {
            const data = response.data;
            this.form.ai_enabled = data.ai_enabled ?? false;
            this.form.prompt_chat_history_enabled =
              data.prompt_chat_history_enabled ?? true;
            this.form.ocr_language = data.ocr_language ?? '';
            this.form.image_max_width_vision =
              data.image_max_width_vision ?? null;
            this.form.image_max_height_vision =
              data.image_max_height_vision ?? null;
            this.form.image_quality_vision = data.image_quality_vision ?? null;
            this.form.image_max_width_tesseract =
              data.image_max_width_tesseract ?? null;
            this.form.image_max_height_tesseract =
              data.image_max_height_tesseract ?? null;
            this.form.asset_similarity_threshold =
              data.asset_similarity_threshold ?? null;
            this.form.asset_max_suggestions =
              data.asset_max_suggestions ?? null;
            this.form.match_auto_accept_threshold =
              data.match_auto_accept_threshold ?? null;
            this.form.duplicate_date_window_days =
              data.duplicate_date_window_days ?? null;
            this.form.duplicate_amount_tolerance_percent =
              data.duplicate_amount_tolerance_percent ?? null;
            this.form.duplicate_similarity_threshold =
              data.duplicate_similarity_threshold ?? null;
            this.form.category_matching_mode =
              data.category_matching_mode ?? 'child_preferred';
            this.warnings = this.normalizeWarnings(data.warnings ?? []);

            this.$emit('ai-processing-changed', this.form.ai_enabled);
          })
          .catch((error) => {
            console.error('Failed to load AI behavior settings:', error);
            toastHelpers.showErrorToast(
              __('Failed to load AI behavior settings.'),
            );
          })
          .finally(() => {
            this.loading = false;
          });
      },
      onSubmit() {
        const _vue = this;
        this.form.busy = true;

        const payload = { ...this.form.data() };

        // Normalize null for optional Tesseract image limit fields
        if (payload.image_max_width_vision === '') {
          payload.image_max_width_vision = null;
        }
        if (payload.image_max_height_vision === '') {
          payload.image_max_height_vision = null;
        }
        if (payload.image_max_width_tesseract === '') {
          payload.image_max_width_tesseract = null;
        }
        if (payload.image_max_height_tesseract === '') {
          payload.image_max_height_tesseract = null;
        }

        this.form
          .patch(this.route('api.v1.ai.settings.update'), payload)
          .then((response) => {
            this.form.ai_enabled = Boolean(
              response.data?.ai_enabled ?? this.form.ai_enabled,
            );
            this.form.prompt_chat_history_enabled = Boolean(
              response.data?.prompt_chat_history_enabled ??
              this.form.prompt_chat_history_enabled,
            );
            this.warnings = this.normalizeWarnings(
              response.data?.warnings ?? [],
            );

            this.$emit('ai-processing-changed', this.form.ai_enabled);

            toastHelpers.showSuccessToast(__('AI behavior settings updated'));
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
      normalizeWarnings(warnings) {
        if (!Array.isArray(warnings)) {
          return [];
        }

        return warnings
          .map((warning) => this.normalizeWarningMessage(warning))
          .filter((warning) => Boolean(warning));
      },
      normalizeWarningMessage(warning) {
        let warningCode = null;
        let warningMessage = null;

        if (typeof warning === 'string') {
          const trimmedWarning = warning.trim();

          try {
            const parsedWarning = JSON.parse(trimmedWarning);

            warningCode = parsedWarning?.code ?? null;
            warningMessage = parsedWarning?.message ?? null;
          } catch {
            warningMessage = trimmedWarning;
          }
        } else if (warning && typeof warning === 'object') {
          warningCode = warning.code ?? null;
          warningMessage = warning.message ?? null;
        }

        if (warningCode === 'NO_ACTIVE_CHILD_CATEGORIES') {
          return __(
            'Child-focused category matching is selected, but you do not have any active child categories. You can continue, but matching may be less specific until child categories are added.',
          );
        }

        return warningMessage;
      },
      __,
    },
  };
</script>
