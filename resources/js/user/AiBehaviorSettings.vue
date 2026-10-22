<template>
  <div id="aiBehaviorSettingsForm" class="card">
    <form
      accept-charset="UTF-8"
      autocomplete="off"
      @submit.prevent="onSubmit"
      @keydown="form.onKeydown($event)"
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
      <div v-if="!sandbox_mode" class="card-body">
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
                  id="ai_enabled"
                  v-model="form.ai_enabled"
                  class="form-check-input"
                  type="checkbox"
                />
                <label class="form-check-label" for="ai_enabled">
                  {{ __('Enable AI document processing') }}
                </label>
              </div>
              <small class="form-text text-muted">
                {{
                  __(
                    'When disabled, new documents are still accepted but stay unprocessed until AI processing is enabled.',
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
                  id="prompt_chat_history_enabled"
                  v-model="form.prompt_chat_history_enabled"
                  class="form-check-input"
                  type="checkbox"
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

          <div class="row mb-3">
            <label class="col-form-label col-sm-4">
              {{ __('Record Automatically') }}
            </label>
            <div class="col-sm-8">
              <div class="form-check form-switch">
                <input
                  id="auto_record_enabled"
                  v-model="form.auto_record_enabled"
                  class="form-check-input"
                  type="checkbox"
                />
                <label class="form-check-label" for="auto_record_enabled">
                  {{ __('Record transactions without review when it is safe') }}
                </label>
              </div>
              <small class="form-text text-muted">
                {{
                  __(
                    'When enabled, a document is recorded as a normal transaction right away if its payee and account are identified for certain and the payee has a consistent history. You can review and confirm what was recorded with the Review filter of your AI documents. Each payee can override the history rule in its own Auto-recording setting. Nothing is recorded unless AI processing is on.',
                  )
                }}
                <a :href="route('payees.auto-record-candidates')">
                  {{ __('Auto-record candidates') }}
                </a>
              </small>
              <HasError field="auto_record_enabled" :form="form" />
            </div>
          </div>

          <div class="row mb-3">
            <label
              for="generic_document_language"
              class="col-form-label col-sm-4"
            >
              {{ __('Document Language') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  id="generic_document_language"
                  v-model="form.generic_document_language"
                  type="text"
                  class="form-control"
                  name="generic_document_language"
                  maxlength="128"
                  :placeholder="__('English')"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Optional free-text document language hint. Use the full language name, such as English or Hungarian, to help AI match and extract document content more precisely.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <small class="form-text text-muted">
                {{
                  __(
                    'Optional. Use the full language name (for example English or Hungarian) to make AI matching and extraction more precise.',
                  )
                }}
              </small>
              <HasError field="generic_document_language" :form="form" />
            </div>
          </div>

          <hr class="my-3" />

          <!-- Document retention -->
          <h6 class="text-muted mb-3">
            {{ __('Document Retention') }}
          </h6>

          <div class="row mb-3">
            <label
              for="document_retention_days"
              class="col-form-label col-sm-4"
            >
              {{ __('Delete finalized documents after (days)') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  id="document_retention_days"
                  v-model.number="form.document_retention_days"
                  type="number"
                  class="form-control"
                  name="document_retention_days"
                  min="1"
                  max="3650"
                  :placeholder="__('Keep forever')"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'Finalized documents older than this are deleted daily, together with their stored files and received emails. The transactions created from them are kept.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <small class="form-text text-muted">
                {{
                  __(
                    'Optional. Documents that are not finalized are never deleted; you get a daily reminder email about the old ones instead. Leave blank to keep everything.',
                  )
                }}
              </small>
              <HasError field="document_retention_days" :form="form" />
            </div>
          </div>

          <hr class="my-3" />

          <!-- Category matching -->
          <h6 class="text-muted mb-3">
            {{ __('Category Matching') }}
          </h6>

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
                  id="category_matching_mode"
                  v-model="form.category_matching_mode"
                  class="form-select"
                  name="category_matching_mode"
                >
                  <option value="best_match">
                    {{ __('Best match') }}
                  </option>
                  <option value="parent_only">
                    {{ __('Parent only') }}
                  </option>
                  <option value="parent_preferred">
                    {{ __('Parent preferred') }}
                  </option>
                  <option value="child_preferred">
                    {{ __('Child preferred') }}
                  </option>
                  <option value="child_only">
                    {{ __('Child only') }}
                  </option>
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

          <!-- Strictness groups: asset matching, duplicate detection, auto-recording -->
          <template v-for="section in strictnessSections" :key="section.key">
            <h6 class="text-muted mb-3">{{ section.title }}</h6>

            <div class="row mb-3">
              <label class="col-form-label col-sm-4">
                {{ __('Strictness') }}
              </label>
              <div class="col-sm-8">
                <div
                  class="btn-group flex-wrap"
                  role="group"
                  :aria-label="section.title"
                >
                  <button
                    v-for="name in presetNames"
                    :key="name"
                    type="button"
                    class="btn"
                    :class="
                      activePreset(section) === name
                        ? 'btn-primary'
                        : 'btn-outline-primary'
                    "
                    :data-testid="`${section.key}-${name}`"
                    @click="applyPreset(section, name)"
                  >
                    {{ presetLabels[name] }}
                  </button>
                  <button
                    type="button"
                    class="btn"
                    :class="
                      activePreset(section) === 'custom'
                        ? 'btn-secondary'
                        : 'btn-outline-secondary'
                    "
                    :data-testid="`${section.key}-custom`"
                    @click="expanded[section.key] = true"
                  >
                    {{ presetLabels.custom }}
                  </button>
                </div>
                <div class="form-text text-muted">
                  {{ section.hint }}
                  <a v-if="section.link" :href="section.link.href">
                    {{ section.link.label }}
                  </a>
                </div>
                <button
                  type="button"
                  class="btn btn-link btn-sm p-0 mt-1"
                  :aria-expanded="expanded[section.key]"
                  :data-testid="`${section.key}-toggle`"
                  @click="expanded[section.key] = !expanded[section.key]"
                >
                  <i
                    class="fa me-1"
                    :class="
                      expanded[section.key]
                        ? 'fa-chevron-down'
                        : 'fa-chevron-right'
                    "
                  ></i
                  >{{
                    expanded[section.key]
                      ? __('Hide values')
                      : __('Customize values')
                  }}
                </button>
              </div>
            </div>

            <div v-show="expanded[section.key]">
              <div
                v-for="field in section.fields"
                :key="field.name"
                class="row mb-3"
              >
                <label :for="field.name" class="col-form-label col-sm-4">
                  {{ field.label }}
                </label>
                <div class="col-sm-8">
                  <div class="input-group">
                    <input
                      :id="field.name"
                      v-model.number="form[field.name]"
                      type="number"
                      class="form-control"
                      :name="field.name"
                      :step="field.step"
                      :min="field.min"
                      :max="field.max"
                    />
                    <span
                      class="input-group-text btn btn-outline-input-info"
                      data-coreui-toggle="tooltip"
                      data-coreui-placement="top"
                      :title="field.help"
                    >
                      <i class="fa fa-info-circle"></i>
                    </span>
                  </div>
                  <HasError :field="field.name" :form="form" />
                </div>
              </div>
            </div>

            <hr class="my-3" />
          </template>

          <!-- OCR & Vision -->
          <h6 class="text-muted mb-3">
            {{ __('OCR & Image Processing') }}
          </h6>

          <div v-if="!tesseractAvailable" class="alert alert-info mb-3">
            <i class="fa fa-circle-info me-1"></i>
            {{
              __(
                'Tesseract OCR is not available in this environment, so Tesseract-specific settings are hidden.',
              )
            }}
          </div>

          <div v-if="tesseractAvailable" class="row mb-3">
            <label for="ocr_language" class="col-form-label col-sm-4">
              {{ __('OCR Language') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  id="ocr_language"
                  v-model="form.ocr_language"
                  type="text"
                  class="form-control"
                  name="ocr_language"
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
                  id="image_max_width_vision"
                  v-model="form.image_max_width_vision"
                  type="number"
                  class="form-control"
                  name="image_max_width_vision"
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
                  id="image_max_height_vision"
                  v-model="form.image_max_height_vision"
                  type="number"
                  class="form-control"
                  name="image_max_height_vision"
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
                  id="image_quality_vision"
                  v-model.number="form.image_quality_vision"
                  type="number"
                  class="form-control"
                  name="image_quality_vision"
                  min="1"
                  max="100"
                />
                <span
                  class="input-group-text btn btn-outline-input-info"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="
                    __(
                      'JPEG quality (1-100) for images sent to Vision AI. Lower values reduce API costs but may impact accuracy.',
                    )
                  "
                >
                  <i class="fa fa-info-circle"></i>
                </span>
              </div>
              <HasError field="image_quality_vision" :form="form" />
            </div>
          </div>

          <div v-if="tesseractAvailable" class="row mb-3">
            <label
              for="image_max_width_tesseract"
              class="col-form-label col-sm-4"
            >
              {{ __('Tesseract Max Width (px)') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  id="image_max_width_tesseract"
                  v-model="form.image_max_width_tesseract"
                  type="number"
                  class="form-control"
                  name="image_max_width_tesseract"
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

          <div v-if="tesseractAvailable" class="row mb-0">
            <label
              for="image_max_height_tesseract"
              class="col-form-label col-sm-4"
            >
              {{ __('Tesseract Max Height (px)') }}
            </label>
            <div class="col-sm-8">
              <div class="input-group">
                <input
                  id="image_max_height_tesseract"
                  v-model="form.image_max_height_tesseract"
                  type="number"
                  class="form-control"
                  name="image_max_height_tesseract"
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
      <div v-else class="card-body">
        <div class="alert alert-warning">
          {{
            __('You are in sandbox mode. You cannot change the AI settings.')
          }}
        </div>
      </div>

      <div v-if="!loading && !sandbox_mode" class="card-footer">
        <SubmitButton class="btn btn-primary" :form="form">
          <i v-show="!form.busy" class="fa me-1 fa-save"></i>{{ __('Save') }}
        </SubmitButton>
      </div>
    </form>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import Form from 'vform';
  import Swal from 'sweetalert2';
  import {
    Button as SubmitButton,
    HasError,
  } from 'vform/src/components/bootstrap5';

  export default {
    name: 'AiBehaviorSettings',
    components: {
      SubmitButton,
      HasError,
    },
    props: {
      tesseractAvailable: {
        type: Boolean,
        default: true,
      },
    },
    emits: ['ai-processing-changed'],
    data: () => ({
      form: new Form({
        ai_enabled: false,
        prompt_chat_history_enabled: true,
        ocr_language: '',
        generic_document_language: '',
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
        same_event_minutes: null,
        auto_record_enabled: false,
        itemization_timeout_days: null,
        auto_record_min_history: null,
        auto_record_wilson_min: null,
        auto_record_amount_tolerance_percent: null,
        match_min_margin: null,
        category_matching_mode: 'child_preferred',
        document_retention_days: null,
      }),
      savedRetentionDays: null,
      // Named value sets of the strictness groups, defined by the server; nothing of them is saved
      presets: {},
      expanded: {
        asset_matching: false,
        duplicate_detection: false,
        auto_record: false,
      },
      loading: true,
      warnings: [],
      sandbox_mode: window.YAFFA.config.sandbox_mode,
    }),
    computed: {
      presetNames() {
        return ['loose', 'balanced', 'strict'];
      },
      presetLabels() {
        return {
          loose: __('Loose'),
          balanced: __('Balanced'),
          strict: __('Strict'),
          custom: __('Custom'),
        };
      },
      strictnessSections() {
        return [
          {
            key: 'asset_matching',
            title: __('Asset Matching'),
            hint: __(
              'How sure the app has to be before it picks a payee or an account without asking the AI. Strict trusts only near-certain matches. Automatic recording relies only on matches that are certain by these values.',
            ),
            fields: [
              {
                name: 'asset_similarity_threshold',
                label: __('Similarity Threshold'),
                step: 0.01,
                min: 0,
                max: 1,
                help: __(
                  'Minimum similarity score (0-1) to suggest an asset match.',
                ),
              },
              {
                name: 'match_auto_accept_threshold',
                label: __('Auto-accept Threshold'),
                step: 0.01,
                min: 0,
                max: 1,
                help: __(
                  'Similarity score (0-1) from which a match is certain: it is taken without asking the AI, and automatic recording accepts it.',
                ),
              },
              {
                name: 'match_min_margin',
                label: __('Match Margin'),
                step: 0.01,
                min: 0,
                max: 1,
                help: __(
                  'The best match has to beat the second best by this much to be certain.',
                ),
              },
              {
                name: 'asset_max_suggestions',
                label: __('Max Suggestions'),
                step: 1,
                min: 1,
                max: 255,
                help: __(
                  'Maximum number of asset match suggestions to return per document.',
                ),
              },
            ],
          },
          {
            key: 'duplicate_detection',
            title: __('Duplicate Transaction Detection'),
            hint: __(
              'How eagerly documents are flagged as possible duplicates of existing transactions. Strict flags more of them.',
            ),
            fields: [
              {
                name: 'duplicate_date_window_days',
                label: __('Date Window (days)'),
                step: 1,
                min: 1,
                max: 255,
                help: __(
                  'Number of days within which two documents with similar amounts are considered potential duplicates.',
                ),
              },
              {
                name: 'duplicate_amount_tolerance_percent',
                label: __('Amount Tolerance (%)'),
                step: 0.01,
                min: 0,
                max: 100,
                help: __(
                  'Percentage tolerance for amount comparison when detecting duplicates.',
                ),
              },
              {
                name: 'duplicate_similarity_threshold',
                label: __('Similarity Threshold'),
                step: 0.01,
                min: 0,
                max: 1,
                help: __(
                  'Minimum similarity score (0-1) to flag two documents as duplicates.',
                ),
              },
              {
                name: 'same_event_minutes',
                label: __('Same Event Window (minutes)'),
                step: 1,
                min: 1,
                max: 255,
                help: __(
                  'Two documents with the same amount and payee, whose times are within this many minutes, are treated as the same purchase.',
                ),
              },
            ],
          },
          {
            key: 'auto_record',
            title: __('Auto-recording'),
            hint: __(
              'How much consistent history a payee needs before its documents are recorded without review. Strict records fewer documents automatically.',
            ),
            link: {
              href: this.route('payees.auto-record-candidates'),
              label: __('Auto-record candidates'),
            },
            fields: [
              {
                name: 'auto_record_min_history',
                label: __('Minimum History'),
                step: 1,
                min: 1,
                max: 255,
                help: __(
                  'A payee needs at least this many past transactions before it can qualify.',
                ),
              },
              {
                name: 'auto_record_wilson_min',
                label: __('Minimum Consistency'),
                step: 0.01,
                min: 0,
                max: 1,
                help: __(
                  'Lower confidence bound (0-1) of how often the payee has a single item in its usual category. 0.8 needs about 11 consistent transactions in a row.',
                ),
              },
              {
                name: 'auto_record_amount_tolerance_percent',
                label: __('Usual Amount Tolerance (%)'),
                step: 0.01,
                min: 0,
                max: 100,
                help: __(
                  'How far an amount may be from the payee’s usual amount.',
                ),
              },
              {
                name: 'itemization_timeout_days',
                label: __('Receipt Wait (days)'),
                step: 1,
                min: 1,
                max: 255,
                help: __(
                  'How long a bank notification of a payee that expects itemization waits for its receipt before it goes to review.',
                ),
              },
            ],
          },
        ];
      },
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
            this.form.generic_document_language =
              data.generic_document_language ?? '';
            this.form.image_max_width_vision =
              data.image_max_width_vision ?? null;
            this.form.image_max_height_vision =
              data.image_max_height_vision ?? null;
            this.form.image_quality_vision = data.image_quality_vision ?? null;
            this.form.image_max_width_tesseract =
              data.image_max_width_tesseract ?? null;
            this.form.image_max_height_tesseract =
              data.image_max_height_tesseract ?? null;
            this.form.auto_record_enabled = data.auto_record_enabled ?? false;
            this.presets = data.presets ?? {};
            this.strictnessSections.forEach((section) => {
              section.fields.forEach(({ name }) => {
                this.form[name] = data[name] ?? null;
              });
            });
            this.form.category_matching_mode =
              data.category_matching_mode ?? 'child_preferred';
            this.form.document_retention_days =
              data.document_retention_days ?? null;
            this.savedRetentionDays = this.form.document_retention_days;
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
      async onSubmit() {
        const _vue = this;

        const payload = { ...this.form.data() };

        if (payload.document_retention_days === '') {
          payload.document_retention_days = null;
        }

        if (
          !(await this.confirmRetentionChange(payload.document_retention_days))
        ) {
          return;
        }

        this.form.busy = true;

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
        if (typeof payload.generic_document_language === 'string') {
          payload.generic_document_language =
            payload.generic_document_language.trim() || null;
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
            this.form.generic_document_language =
              response.data?.generic_document_language ?? '';
            this.form.document_retention_days =
              response.data?.document_retention_days ?? null;
            this.savedRetentionDays = this.form.document_retention_days;
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
      // Enabling or changing the retention is destructive: when a Google Drive import leaves the
      // imported files in the folder, deleted documents can be imported again as duplicates.
      async confirmRetentionChange(retentionDays) {
        const driveKeepsFiles =
          window.aiSettingsPageMeta?.drive_keeps_imported_files === true;

        if (
          !retentionDays ||
          retentionDays === this.savedRetentionDays ||
          !driveKeepsFiles
        ) {
          return true;
        }

        const result = await Swal.fire({
          icon: 'warning',
          text: __('maintenance.aiDocumentOldFiles.driveWarning'),
          confirmButtonText: __('Confirm'),
          cancelButtonText: __('Cancel'),
          showCancelButton: true,
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary ms-3',
          },
        });

        return result.isConfirmed;
      },
      // The name of the preset whose values the form currently holds, else 'custom'. Never stored.
      activePreset(section) {
        const sectionPresets = this.presets[section.key] ?? {};

        return (
          Object.keys(sectionPresets).find((name) =>
            Object.entries(sectionPresets[name]).every(
              ([field, value]) =>
                Math.abs(Number(this.form[field]) - Number(value)) < 1e-9,
            ),
          ) ?? 'custom'
        );
      },
      applyPreset(section, name) {
        Object.entries(this.presets[section.key]?.[name] ?? {}).forEach(
          ([field, value]) => {
            this.form[field] = value;
          },
        );
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
