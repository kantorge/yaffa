<template>
  <div class="staging-upload-tool">
    <h5>{{ __('Upload Transactions for Staging') }}</h5>
    <form @submit.prevent="onSubmit">
      <div class="mb-3">
        <label for="sourceSelect" class="form-label">{{ __('Select Source') }}</label>
        <select v-model="selectedSource" id="sourceSelect" class="form-select" required>
          <option disabled value="">-- {{ __('Choose Source') }} --</option>
          <option value="MoneyHub">MoneyHub</option>
          <option value="WiseAlpha">WiseAlpha</option>
          <option value="Trading212">Trading 212</option>
          <option value="CompanyJSON">Company JSON</option>
        </select>
      </div>
      <div class="mb-3">
        <label for="fileInput" class="form-label">{{ __('File (CSV, XLSX, or JSON)') }}</label>
        <input type="file" id="fileInput" class="form-control" accept=".csv,.xlsx,.json" @change="onFileChange" required />
      </div>
      <button type="submit" class="btn btn-primary">{{ __('Upload & Stage') }}</button>
    </form>
    <div v-if="uploadResult" class="mt-3">
      <div v-if="uploadResult.success" class="alert alert-success">
        {{ uploadResult.message }}<br />
        <span v-if="uploadResult.staging_file">Staging file: {{ uploadResult.staging_file }}</span>
      </div>
      <div v-else class="alert alert-danger">{{ uploadResult.message }}</div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      selectedSource: '',
      file: null,
      uploadResult: null,
    };
  },
  methods: {
    onFileChange(e) {
      this.file = e.target.files[0];
    },
    async onSubmit() {
      if (!this.selectedSource || !this.file) return;
      const formData = new FormData();
      formData.append('source', this.selectedSource);
      formData.append('file', this.file);
      try {
        const res = await fetch('/api/staging-upload', {
          method: 'POST',
          body: formData,
        });
        const result = await res.json();
        this.uploadResult = result;
      } catch (e) {
        this.uploadResult = { success: false, message: e.message };
      }
    },
  },
};
</script>

<style scoped>
.staging-upload-tool {
  max-width: 500px;
  margin: 0 auto;
}
</style>
