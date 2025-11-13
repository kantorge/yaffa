<template>
  <div class="investment-upload-tool">
    <h5>{{ __('Upload WiseAlpha Investment Statement') }}</h5>
    <form @submit.prevent="onSubmit">
      <div class="mb-3">
        <label for="accountSelect" class="form-label">{{ __('Select Account') }}</label>
        <select v-model="selectedAccount" id="accountSelect" class="form-select" required>
          <option v-for="account in accounts" :key="account.id" :value="account.id">
            {{ account.name }}
          </option>
        </select>
      </div>
      <div class="mb-3">
        <label for="csvFile" class="form-label">{{ __('CSV File') }}</label>
        <input type="file" id="csvFile" class="form-control" accept=".csv" @change="onFileChange" required />
      </div>
      <button type="submit" class="btn btn-primary">{{ __('Upload & Import') }}</button>
    </form>
    <div v-if="uploadResult" class="mt-3">
      <div v-if="uploadResult.success" class="alert alert-success">{{ uploadResult.message }}</div>
      <div v-else class="alert alert-danger">{{ uploadResult.message }}</div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      accounts: [],
      selectedAccount: '',
      file: null,
      uploadResult: null,
    };
  },
  mounted() {
    // Fetch accounts for dropdown
    fetch('/api/accounts')
      .then(res => res.json())
      .then(data => {
        this.accounts = data;
      });
  },
  methods: {
    onFileChange(e) {
      this.file = e.target.files[0];
    },
    async onSubmit() {
      if (!this.selectedAccount || !this.file) return;
      const formData = new FormData();
      formData.append('account_id', this.selectedAccount);
      formData.append('file', this.file);
      try {
        const res = await fetch('/api/investment-upload/wisealpha', {
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
.investment-upload-tool {
  max-width: 500px;
  margin: 0 auto;
}
</style>
