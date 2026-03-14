<template>
  <div class="card mb-3">
    <div class="card-header">
      <div class="card-title">
        <i class="fa fa-circle-info text-info me-1"></i>
        {{ __('AI Processing Environment') }}
      </div>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-lg-6">
          <h6 class="mb-2">{{ __('Incoming Receipt Emails') }}</h6>
          <div class="small mb-1">
            <strong>{{ __('Enabled') }}:</strong>
            <span
              :class="incomingEmail.enabled ? 'text-success' : 'text-muted'"
            >
              {{ incomingEmail.enabled ? __('Yes') : __('No') }}
            </span>
          </div>
          <div class="small mb-1">
            <strong>{{ __('Configured') }}:</strong>
            <span
              :class="
                incomingEmail.configured ? 'text-success' : 'text-warning'
              "
            >
              {{ incomingEmail.configured ? __('Yes') : __('No') }}
            </span>
          </div>
          <div class="small">
            <strong>{{ __('Recipient') }}:</strong>
            <span class="font-monospace">{{ incomingEmailRecipient }}</span>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <h6 class="mb-2">{{ __('OCR (Tesseract)') }}</h6>
          <div class="small mb-1">
            <strong>{{ __('Enabled') }}:</strong>
            <span
              :class="ocr.tesseract_enabled ? 'text-success' : 'text-muted'"
            >
              {{ ocr.tesseract_enabled ? __('Yes') : __('No') }}
            </span>
          </div>
          <div class="small mb-1">
            <strong>{{ __('Mode') }}:</strong>
            <span>{{ ocrModeLabel }}</span>
          </div>
          <div class="small">
            <strong>{{ __('Available') }}:</strong>
            <span
              :class="ocr.tesseract_available ? 'text-success' : 'text-warning'"
            >
              {{ ocr.tesseract_available ? __('Yes') : __('No') }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-xl-6 mb-3">
      <ai-provider-settings
        :ai-processing-enabled="aiProcessingEnabled"
      ></ai-provider-settings>
    </div>
    <div class="col-xl-6 mb-3">
      <google-drive-settings
        :ai-processing-enabled="aiProcessingEnabled"
      ></google-drive-settings>
    </div>
  </div>
  <div class="row">
    <div class="col-12 mb-3">
      <ai-behavior-settings
        :tesseract-available="ocr.tesseract_available"
        @ai-processing-changed="onAiProcessingChanged"
      ></ai-behavior-settings>
    </div>
  </div>
</template>

<script>
  import AiProviderSettings from './AiProviderSettings.vue';
  import GoogleDriveSettings from './GoogleDriveSettings.vue';
  import AiBehaviorSettings from './AiBehaviorSettings.vue';

  export default {
    name: 'AiSettings',
    components: {
      'ai-provider-settings': AiProviderSettings,
      'google-drive-settings': GoogleDriveSettings,
      'ai-behavior-settings': AiBehaviorSettings,
    },
    data: () => ({
      aiProcessingEnabled: true,
      incomingEmail: window.aiSettingsPageMeta?.incoming_email || {
        enabled: false,
        configured: false,
        recipient: null,
      },
      ocr: window.aiSettingsPageMeta?.ocr || {
        tesseract_enabled: false,
        tesseract_available: false,
        tesseract_mode: 'binary',
      },
    }),
    computed: {
      incomingEmailRecipient() {
        return this.incomingEmail.recipient || __('Not configured');
      },
      ocrModeLabel() {
        return this.ocr.tesseract_mode === 'http'
          ? __('HTTP service')
          : __('Local binary');
      },
    },
    mounted() {
      this.loadAiSettingsState();
    },
    methods: {
      loadAiSettingsState() {
        axios
          .get(this.route('api.v1.ai.settings.show'))
          .then((response) => {
            this.aiProcessingEnabled = Boolean(response.data?.ai_enabled);
          })
          .catch(() => {
            this.aiProcessingEnabled = false;
          });
      },
      onAiProcessingChanged(aiProcessingEnabled) {
        this.aiProcessingEnabled = Boolean(aiProcessingEnabled);
      },
      __,
    },
  };
</script>
