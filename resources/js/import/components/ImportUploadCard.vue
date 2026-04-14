<template>
  <form @submit.prevent="submitUpload" class="row g-3 align-items-end">
    <div :class="sourceType === 'csv' ? 'col-lg-8' : 'col-lg-8'">
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
      <div class="col-12">
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

    <div class="col-lg-4 d-grid">
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
          :style="{ width: `${progress}%` }"
        >
          {{ progress }}%
        </div>
      </div>
    </div>
  </form>

  <div v-if="error" class="alert alert-danger mt-3 mb-0" role="alert">
    {{ error }}
  </div>
</template>

<script>
  import { computed, ref } from 'vue';

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
    emits: ['submit', 'update:selectedProfileId'],
    setup(props, { emit }) {
      const selectedFile = ref(null);
      const fileInput = ref(null);

      const systemProfiles = computed(() =>
        props.profiles.filter((p) => p.type === 'system'),
      );
      const userProfiles = computed(() =>
        props.profiles.filter((p) => p.type === 'user'),
      );

      const isSubmitDisabled = computed(() => {
        if (props.loading || !props.accountId || !selectedFile.value) {
          return true;
        }

        if (props.sourceType === 'csv' && !props.selectedProfileId) {
          return true;
        }

        return false;
      });

      const acceptedFileTypes = computed(() => {
        return props.sourceType === 'qif' ? '.qif,.txt' : '.csv';
      });

      const handleFileSelection = (event) => {
        const files = event.target.files;
        selectedFile.value = files && files.length > 0 ? files[0] : null;
      };

      const onProfileChange = (value) => {
        const numericValue = value ? Number(value) : null;
        emit('update:selectedProfileId', numericValue);
      };

      const submitUpload = () => {
        if (isSubmitDisabled.value) {
          return;
        }

        emit('submit', {
          file: selectedFile.value,
        });
      };

      const reset = () => {
        selectedFile.value = null;
        if (fileInput.value) {
          fileInput.value.value = '';
        }
      };

      return {
        selectedFile,
        fileInput,
        systemProfiles,
        userProfiles,
        isSubmitDisabled,
        acceptedFileTypes,
        handleFileSelection,
        onProfileChange,
        submitUpload,
        reset,
      };
    },
  };
</script>
