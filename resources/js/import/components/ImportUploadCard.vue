<template>
  <form @submit.prevent="submitUpload" class="row g-3 align-items-end">
    <div class="col-12 col-lg-5">
      <label class="form-label" for="import-file">
        {{ __('File') }}
        <span class="form-text"
          >(
          {{
            sourceType === 'qif'
              ? __('Accepted formats: :format', { format: '.qif, .txt' })
              : __('Accepted formats: :format', { format: '.csv' })
          }}
          )
        </span>
      </label>
      <input
        id="import-file"
        ref="fileInput"
        type="file"
        class="form-control"
        :accept="acceptedFileTypes"
        :disabled="loading"
        @change="handleFileSelection"
      />
    </div>

    <template v-if="sourceType === 'csv'">
      <div class="col-12 col-lg-4">
        <label class="form-label" for="csv-profile-select">
          {{ __('CSV import profile') }}
        </label>
        <select
          id="csv-profile-select"
          class="form-select"
          :value="selectedProfileId"
          :disabled="loading || loadingProfiles"
          @change="onProfileChange($event.target.value)"
        >
          <option :value="null">
            {{
              loadingProfiles
                ? __('Loading profiles...')
                : __('— Select a profile —')
            }}
          </option>
          <optgroup v-if="systemProfiles.length" :label="__('System profiles')">
            <option
              v-for="profile in systemProfiles"
              :key="profile.id"
              :value="profile.id"
            >
              {{ profile.name }}
            </option>
          </optgroup>
          <optgroup v-if="userProfiles.length" :label="__('My profiles')">
            <option
              v-for="profile in userProfiles"
              :key="profile.id"
              :value="profile.id"
            >
              {{ profile.name }}
            </option>
          </optgroup>
        </select>
      </div>
    </template>

    <template v-if="sourceType === 'qif'">
      <div class="col-12 col-lg-4">
        <label class="form-label" for="qif-profile-select">
          {{ __('QIF field mapping profile') }}
          <span class="form-text">({{ __('optional') }})</span>
        </label>
        <select
          id="qif-profile-select"
          class="form-select"
          :value="selectedQifProfileId ?? ''"
          :disabled="loading || loadingQifProfiles"
          @change="onQifProfileChange($event.target.value)"
        >
          <option value="">{{ __('— Standard QIF mapping (default) —') }}</option>
          <optgroup v-if="qifSystemProfiles.length" :label="__('System profiles')">
            <option
              v-for="profile in qifSystemProfiles"
              :key="profile.id"
              :value="profile.id"
            >
              {{ profile.name }}
            </option>
          </optgroup>
          <optgroup v-if="qifUserProfiles.length" :label="__('My profiles')">
            <option
              v-for="profile in qifUserProfiles"
              :key="profile.id"
              :value="profile.id"
            >
              {{ profile.name }}
            </option>
          </optgroup>
        </select>
      </div>
    </template>

    <div class="col-12 col-lg-3 d-grid">
      <button
        type="submit"
        class="btn btn-primary"
        :disabled="isSubmitDisabled"
      >
        <span
          v-if="loading"
          class="spinner-border spinner-border-sm me-2"
          role="status"
          aria-hidden="true"
        ></span>
        {{ loading ? __('Parsing...') : __('Upload and parse') }}
      </button>
    </div>

    <div v-if="loading" class="col-12">
      <div
        class="progress"
        role="progressbar"
        :aria-valuenow="progress"
        aria-valuemin="0"
        aria-valuemax="100"
      >
        <div
          class="progress-bar progress-bar-striped progress-bar-animated"
          :class="{ 'bg-info': isProcessing }"
          :style="{ width: isProcessing ? '100%' : `${progress}%` }"
        >
          {{ isProcessing ? __('Parsing on server…') : `${progress}%` }}
        </div>
      </div>
    </div>
  </form>

  <div v-if="error" class="alert alert-danger mt-3 mb-0" role="alert">
    {{ error }}
  </div>
</template>

<script>
  export default {
    name: 'ImportUploadCard',
    props: {
      sourceType: {
        type: String,
        required: true,
      },
      accountId: {
        type: String,
        required: true,
      },
      profiles: {
        type: Array,
        default: () => [],
      },
      selectedProfileId: {
        type: Number,
        default: null,
      },
      loadingProfiles: {
        type: Boolean,
        default: false,
      },
      qifProfiles: {
        type: Array,
        default: () => [],
      },
      selectedQifProfileId: {
        type: Number,
        default: null,
      },
      loadingQifProfiles: {
        type: Boolean,
        default: false,
      },
      loading: {
        type: Boolean,
        default: false,
      },
      progress: {
        type: Number,
        default: 0,
      },
      error: {
        type: String,
        default: null,
      },
    },
    emits: ['submit', 'update:selectedProfileId', 'update:selectedQifProfileId'],
    data() {
      return {
        selectedFile: null,
      };
    },
    computed: {
      systemProfiles() {
        return this.profiles.filter((p) => p.type === 'system');
      },
      userProfiles() {
        return this.profiles.filter((p) => p.type === 'user');
      },
      qifSystemProfiles() {
        return this.qifProfiles.filter((p) => p.type === 'system');
      },
      qifUserProfiles() {
        return this.qifProfiles.filter((p) => p.type === 'user');
      },
      isSubmitDisabled() {
        if (this.loading || !this.accountId || !this.selectedFile) {
          return true;
        }
        if (this.sourceType === 'csv' && !this.selectedProfileId) {
          return true;
        }
        return false;
      },
      acceptedFileTypes() {
        return this.sourceType === 'qif' ? '.qif,.txt' : '.csv';
      },
      isProcessing() {
        return this.loading && this.progress >= 100;
      },
    },
    methods: {
      handleFileSelection(event) {
        const files = event.target.files;
        this.selectedFile = files && files.length > 0 ? files[0] : null;
      },
      onProfileChange(value) {
        const numericValue = value ? Number(value) : null;
        this.$emit('update:selectedProfileId', numericValue);
      },
      onQifProfileChange(value) {
        const numericValue = value ? Number(value) : null;
        this.$emit('update:selectedQifProfileId', numericValue);
      },
      submitUpload() {
        if (this.isSubmitDisabled) {
          return;
        }
        this.$emit('submit', { file: this.selectedFile });
      },
      reset() {
        this.selectedFile = null;
        if (this.$refs.fileInput) {
          this.$refs.fileInput.value = '';
        }
      },
    },
  };
</script>
