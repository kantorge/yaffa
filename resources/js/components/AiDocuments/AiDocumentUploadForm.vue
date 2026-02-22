<template>
  <div class="modal" tabindex="-1" :id="modalId" ref="modalElement">
    <div class="modal-dialog">
      <div class="modal-content">
        <form @submit.prevent="onSubmit" autocomplete="off">
          <div class="modal-header">
            <h5 class="modal-title">{{ __('Upload document') }}</h5>
            <button
              type="button"
              class="btn-close"
              data-coreui-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <!-- Error Alert -->
            <div
              v-if="errors.length > 0"
              class="alert alert-danger alert-dismissible fade show"
              role="alert"
            >
              <strong>{{ __('Error!') }}</strong>
              <ul class="mb-0 mt-2">
                <li v-for="error in errors" :key="error">{{ error }}</li>
              </ul>
              <button
                type="button"
                class="btn-close"
                @click="errors = []"
              ></button>
            </div>

            <!-- Success Alert -->
            <div
              v-if="successMessage"
              class="alert alert-success alert-dismissible fade show"
              role="alert"
            >
              {{ successMessage }}
              <button
                type="button"
                class="btn-close"
                @click="successMessage = ''"
              ></button>
            </div>

            <!-- Warning Alert -->
            <div
              v-if="!warningDismissed"
              class="alert alert-warning alert-dismissible fade show"
              role="alert"
            >
              <i class="fa fa-exclamation-triangle me-2"></i>
              <strong>{{ __('Important:') }}</strong>
              {{
                __(
                  'Once saved, this document cannot be modified and will be automatically processed. Please review your documents and custom instructions before submitting.',
                )
              }}
              <button
                type="button"
                class="btn-close"
                @click="dismissWarning"
                aria-label="Close"
              ></button>
            </div>

            <!-- File Upload Section -->
            <div class="mb-4">
              <label class="form-label">{{ __('Files') }}</label>
              <div
                class="border-2 border-dashed rounded p-4 text-center cursor-pointer file-drop-zone"
                @dragover.prevent="isDraggingOver = true"
                @dragleave.prevent="isDraggingOver = false"
                @drop.prevent="handleFileDrop"
                :class="{ 'bg-light': isDraggingOver }"
              >
                <input
                  ref="fileInput"
                  type="file"
                  multiple
                  class="d-none"
                  @change="handleFileSelection"
                  :accept="allowedMimeTypes"
                />
                <div
                  v-if="selectedFiles.length === 0"
                  class="pointer-events-none"
                >
                  <i
                    class="fa fa-cloud-upload fa-3x text-muted mb-2 d-block"
                  ></i>
                  <p class="text-muted mb-1">
                    {{ __('Drag and drop files here, or click to select') }}
                  </p>
                  <p class="text-muted small">
                    {{
                      __(
                        'Supported formats: PDF, JPG, PNG, TXT (max :maxFilesPerSubmission files, :maxFileSize MB each)',
                        {
                          maxFilesPerSubmission: maxFilesPerSubmission,
                          maxFileSize: maxFileSize,
                        },
                      )
                    }}
                  </p>
                </div>
                <div v-else class="pointer-events-none">
                  <p class="text-success mb-2">
                    <i class="fa fa-check-circle"></i>
                    {{ selectedFiles.length }}
                    {{ selectedFiles.length === 1 ? __('file') : __('files') }}
                    {{ __('selected') }}
                  </p>
                  <ul class="list-unstyled small mb-2">
                    <li v-for="file in selectedFiles" :key="file.name">
                      <span
                        class="badge bg-info me-2"
                        @click="removeFile(file.name)"
                        style="cursor: pointer"
                        :title="__('Click to remove')"
                      >
                        <i class="fa fa-times"></i>
                        {{ file.name }}
                      </span>
                    </li>
                  </ul>
                  <small class="text-muted">
                    {{
                      __(
                        'Click the area to add more files, or click badges to remove',
                      )
                    }}
                  </small>
                </div>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary mt-3"
                  @click="$refs.fileInput.click()"
                >
                  <i class="fa fa-plus"></i>
                  {{
                    selectedFiles.length > 0
                      ? __('Add more files')
                      : __('Select files')
                  }}
                </button>
              </div>
              <small class="form-text text-muted d-block mt-2">
                {{ __('Maximum 10 files per submission') }}
              </small>
            </div>

            <!-- Custom Prompt Section -->
            <div class="mb-3">
              <label for="customPrompt" class="form-label">
                {{ __('Custom Processing Instructions') }}
                <span class="text-muted">({{ __('optional') }})</span>
              </label>
              <textarea
                id="customPrompt"
                class="form-control"
                v-model="form.customPrompt"
                :placeholder="
                  __(
                    'e.g., This receipt is in French. The account name cannot be extracted, please use Bank account of John.',
                  )
                "
                rows="4"
                maxlength="5000"
              ></textarea>
              <small class="form-text text-muted">
                {{ form.customPrompt.length }}/5000
              </small>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-coreui-dismiss="modal"
              :disabled="isSubmitting"
            >
              {{ __('Cancel') }}
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="selectedFiles.length === 0 || isSubmitting"
            >
              <span v-if="isSubmitting">
                <span
                  class="spinner-border spinner-border-sm me-2"
                  role="status"
                  aria-hidden="true"
                ></span>
                {{ __('Uploading...') }}
              </span>
              <span v-else>
                <i class="fa fa-upload me-2"></i>
                {{ __('Upload and Process') }}
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue';
  import { __ } from '@/i18n';

  const props = defineProps({
    modalId: {
      type: String,
      default: 'aiDocumentUploadModal',
    },
  });

  const emit = defineEmits(['document-created']);

  const modalElement = ref(null);
  const fileInput = ref(null);
  const modal = ref(null);

  const selectedFiles = ref([]);
  const isDraggingOver = ref(false);
  const isSubmitting = ref(false);
  const errors = ref([]);
  const successMessage = ref('');
  const warningDismissed = ref(false);

  const form = reactive({
    customPrompt: '',
  });

  const maxFilesPerSubmission =
    window.aiDocumentConfig?.maxFilesPerSubmission || 5;
  const maxFileSize = window.aiDocumentConfig?.maxFileSize || 20;
  // Restrictive default for security reasons.
  const allowedTypes = window.aiDocumentConfig?.allowedTypes || ['txt'];

  const allowedMimeTypes = computed(() => {
    const mimeMap = {
      pdf: 'application/pdf',
      jpg: 'image/jpeg',
      jpeg: 'image/jpeg',
      png: 'image/png',
      txt: 'text/plain',
    };
    return allowedTypes.map((type) => mimeMap[type] || '').join(',');
  });

  onMounted(() => {
    if (modalElement.value) {
      modal.value = new coreui.Modal(modalElement.value);
    }

    // Fetch warning dismissal state
    fetch(
      route('api.user.preference.get', {
        key: 'dismissAiDocumentUploadWarning',
      }),
    )
      .then((response) => response.json())
      .then((data) => {
        warningDismissed.value = data.value;
      })
      .catch((error) => {
        console.error('Failed to fetch warning dismissal state:', error);
      });
  });

  const handleFileSelection = (event) => {
    const files = Array.from(event.target.files || []);
    addFiles(files);
  };

  const handleFileDrop = (event) => {
    isDraggingOver.value = false;
    const files = Array.from(event.dataTransfer.files || []);
    addFiles(files);
  };

  const addFiles = (files) => {
    errors.value = [];

    // Validate total file count
    if (selectedFiles.value.length + files.length > maxFilesPerSubmission) {
      errors.value.push(
        __(
          `You can upload a maximum of ${maxFilesPerSubmission} files per submission. You currently have ${selectedFiles.value.length} files selected.`,
        ),
      );
      return;
    }

    const validFiles = [];
    for (const file of files) {
      // Check file type
      const extension = file.name.split('.').pop().toLowerCase();
      if (!allowedTypes.includes(extension)) {
        errors.value.push(
          __(
            `File "${file.name}" has an unsupported format. Allowed formats: ${allowedTypes.join(', ')}`,
          ),
        );
        continue;
      }

      // Check file size
      if (file.size > maxFileSize * 1024 * 1024) {
        errors.value.push(
          __(
            `File "${file.name}" exceeds the maximum size of ${maxFileSize}MB`,
          ),
        );
        continue;
      }

      // Check for duplicates
      if (selectedFiles.value.some((f) => f.name === file.name)) {
        errors.value.push(__(`File "${file.name}" is already selected`));
        continue;
      }

      validFiles.push(file);
    }

    selectedFiles.value.push(...validFiles);

    // Reset file input
    if (fileInput.value) {
      fileInput.value.value = '';
    }
  };

  const removeFile = (fileName) => {
    selectedFiles.value = selectedFiles.value.filter(
      (f) => f.name !== fileName,
    );
  };

  const onSubmit = async () => {
    if (selectedFiles.value.length === 0) {
      errors.value = [__('Please select at least one file')];
      return;
    }

    isSubmitting.value = true;
    errors.value = [];

    try {
      const formData = new FormData();

      // Add files
      for (const file of selectedFiles.value) {
        formData.append('files[]', file);
      }

      // Add custom prompt if provided
      if (form.customPrompt.trim()) {
        formData.append('custom_prompt', form.customPrompt);
      }

      const response = await fetch(route('api.documents.store'), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
            .content,
        },
        body: formData,
      });

      if (response.ok) {
        const data = await response.json();
        successMessage.value = __(
          'Document uploaded successfully and queued for processing. You will receive a notification when processing is complete.',
        );

        // Reset form
        selectedFiles.value = [];
        form.customPrompt = '';

        // Emit event for parent to refresh the list
        emit('document-created', data);

        // Close modal after 1.5 seconds
        setTimeout(() => {
          modal.value?.hide();
        }, 1500);
      } else {
        const data = await response.json();

        // Handle validation errors
        if (data.errors) {
          Object.keys(data.errors).forEach((key) => {
            errors.value.push(
              Array.isArray(data.errors[key])
                ? data.errors[key].join('; ')
                : data.errors[key],
            );
          });
        } else if (data.message) {
          errors.value.push(data.message);
        } else {
          errors.value.push(
            __('An error occurred while uploading the document'),
          );
        }
      }
    } catch (error) {
      errors.value.push(__('Network error: ' + error.message));
    } finally {
      isSubmitting.value = false;
    }
  };

  const dismissWarning = async () => {
    try {
      const response = await fetch(
        route('api.user.preference.set', {
          key: 'dismissAiDocumentUploadWarning',
        }),
        {
          method: 'PUT',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
              .content,
            'Content-Type': 'application/json',
          },
        },
      );

      if (response.ok) {
        warningDismissed.value = true;
      }
    } catch (error) {
      console.error('Failed to dismiss warning:', error);
    }
  };

  defineExpose({
    show: () => modal.value?.show(),
    hide: () => modal.value?.hide(),
  });
</script>

<style scoped>
  .file-drop-zone {
    transition: background-color 0.2s;
    cursor: pointer;
    border-width: 2px;
    border-style: dashed;
  }

  .file-drop-zone:hover {
    background-color: #f0f0f0;
  }

  .file-drop-zone.bg-light {
    background-color: #e7f3ff !important;
    border-color: #0d6efd;
  }

  .pointer-events-none {
    pointer-events: none;
  }
</style>
