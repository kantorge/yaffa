<template>
  <div class="row">
    <div class="col-12 col-lg-4">
      <div v-if="!hasFiles" class="text-muted">
        {{ __('No files available') }}
      </div>
      <div v-else class="list-group">
        <button
          v-for="file in files"
          :key="file.id"
          class="list-group-item list-group-item-action"
          :class="{
            active: selectedFile && selectedFile.id === file.id,
          }"
          type="button"
          @click="selectFile(file)"
        >
          <i class="fa fa-fw fa-file me-2"></i>
          {{ file.file_name }}
        </button>
      </div>
    </div>
    <div class="col-12 col-lg-8">
      <div v-if="!selectedFile" class="text-muted">
        {{ __('Select a file to preview') }}
      </div>
      <div v-else>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">{{ selectedFile.file_name }}</h5>
          <a
            class="btn btn-sm btn-outline-primary"
            :href="downloadUrl(selectedFile)"
          >
            <i class="fa fa-fw fa-download"></i>
            {{ __('Download') }}
          </a>
        </div>
        <div v-if="isImage(selectedFile)" class="border rounded p-2">
          <img :src="previewUrl(selectedFile)" class="img-fluid" />
        </div>
        <iframe
          v-else
          class="w-100 border rounded"
          style="min-height: 500px"
          :src="previewUrl(selectedFile)"
        ></iframe>
      </div>
    </div>
  </div>
</template>

<script setup>
  import { computed, ref, watch } from 'vue';
  import { __ } from '../../helpers';

  const props = defineProps({
    files: {
      type: Array,
      default: () => [],
    },
    aiDocumentId: {
      type: [Number, String],
      required: true,
    },
  });

  const selectedFile = ref(null);

  const hasFiles = computed(() => props.files && props.files.length > 0);

  watch(
    () => props.files,
    (newFiles) => {
      if (!newFiles || newFiles.length === 0) {
        selectedFile.value = null;
        return;
      }

      if (!selectedFile.value) {
        selectedFile.value = newFiles[0];
        return;
      }

      const stillExists = newFiles.some(
        (file) => file.id === selectedFile.value?.id,
      );
      if (!stillExists) {
        selectedFile.value = newFiles[0];
      }
    },
    { immediate: true },
  );

  const isImage = (file) => ['jpg', 'jpeg', 'png'].includes(file.file_type);

  const previewUrl = (file) =>
    window.route('ai-documents.files.show', {
      aiDocument: props.aiDocumentId,
      aiDocumentFile: file.id,
    });

  const downloadUrl = (file) => `${previewUrl(file)}?download=1`;

  const selectFile = (file) => {
    selectedFile.value = file;
  };
</script>
