<template>
  <div class="card mb-3">
    <div class="card-header">
      <div class="card-title">{{ __('Upload file') }}</div>
    </div>
    <div class="card-body">
      <form @submit.prevent="submitUpload" class="row g-3 align-items-end">
        <div class="col-lg-8">
          <label class="form-label" for="import-file">{{ __('File') }}</label>
          <input
            id="import-file"
            ref="fileInput"
            type="file"
            class="form-control"
            :accept="acceptedFileTypes"
            :disabled="isDisabled"
            @change="handleFileSelection"
          />
          <div class="form-text">
            {{
              sourceType === 'qif'
                ? __('Accepted formats: .qif, .txt')
                : __('Accepted formats: .csv')
            }}
          </div>
        </div>

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
      </form>

      <div v-if="loading" class="mt-3">
        <div
          class="progress"
          role="progressbar"
          :aria-valuenow="progress"
          aria-valuemin="0"
          aria-valuemax="100"
        >
          <div class="progress-bar" :style="{ width: `${progress}%` }">
            {{ progress }}%
          </div>
        </div>
      </div>

      <div v-if="error" class="alert alert-danger mt-3 mb-0" role="alert">
        {{ error }}
      </div>
    </div>
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
    emits: ['submit'],
    setup(props, { emit }) {
      const selectedFile = ref(null);
      const fileInput = ref(null);

      const isDisabled = computed(
        () => props.loading || props.sourceType === 'csv',
      );
      const isSubmitDisabled = computed(() => {
        return isDisabled.value || !props.accountId || !selectedFile.value;
      });
      const acceptedFileTypes = computed(() => {
        return props.sourceType === 'qif' ? '.qif,.txt' : '.csv';
      });

      const handleFileSelection = (event) => {
        const files = event.target.files;
        selectedFile.value = files && files.length > 0 ? files[0] : null;
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
        isDisabled,
        isSubmitDisabled,
        acceptedFileTypes,
        handleFileSelection,
        submitUpload,
        reset,
      };
    },
  };
</script>
