<template>
  <div class="moneyhub-upload-tool">
    <h5>{{ __('Upload MoneyHub Transactions') }}</h5>
    
    <!-- Success Message -->
    <div v-if="importResult" class="alert" :class="importResult.success ? 'alert-success' : 'alert-danger'" role="alert">
      <h6>{{ importResult.success ? __('Import Completed') : __('Import Failed') }}</h6>
      <p>{{ importResult.message }}</p>
      <div v-if="importResult.success">
        <ul>
          <li>{{ __('Created') }}: {{ importResult.created }}</li>
          <li v-if="importResult.skipped > 0">{{ __('Skipped') }}: {{ importResult.skipped }}</li>
        </ul>
      </div>
      <div v-if="importResult.errors && importResult.errors.length > 0">
        <strong>{{ __('Errors') }}:</strong>
        <ul>
          <li v-for="(error, idx) in importResult.errors" :key="idx">{{ error }}</li>
        </ul>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h6>{{ __('Configuration & Upload') }}</h6>
      </div>
      <div class="card-body">
        <form @submit.prevent="onSubmit">
          <!-- Account Selection -->
          <div class="mb-3">
            <label for="accountSelect" class="form-label">{{ __('Target Account') }} *</label>
            <select id="accountSelect" v-model="selectedAccountId" class="form-select" required>
              <option value="">-- {{ __('Select an account') }} --</option>
              <optgroup v-for="group in accountGroups" :key="group" :label="group">
                <option v-for="account in accountsByGroup[group]" :key="account.id" :value="account.id">
                  {{ account.name }} {{ account.alias ? ' (' + __('alias set') + ')' : '' }}
                </option>
              </optgroup>
            </select>
            <small class="form-text text-muted">
              {{ __('Select the YAFFA account to import transactions into. Set import aliases on accounts to enable automatic matching.') }}
            </small>
          </div>

          <!-- File Upload -->
          <div class="mb-3">
            <label for="fileInput" class="form-label">{{ __('File (CSV or TXT)') }} *</label>
            <input type="file" id="fileInput" class="form-control" accept=".csv,.txt" @change="onFileChange" required />
          </div>

          <!-- Preview Table -->
          <div v-if="filePreview && filePreview.length > 0" class="mb-3">
            <h6>{{ __('File Preview') }} ({{ __('first 5 rows') }})</h6>
            <div class="table-responsive">
              <table class="table table-sm table-bordered table-striped">
                <thead>
                  <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Amount') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Account') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, index) in filePreview.slice(0, 5)" :key="index">
                    <td>{{ row.date }}</td>
                    <td>{{ row.amount }}</td>
                    <td>{{ row.description }}</td>
                    <td>{{ row.category }}</td>
                    <td>{{ row.account }}</td>
                  </tr>
                </tbody>
              </table>
              <small class="text-muted">{{ __('Total rows') }}: {{ filePreview.length }}</small>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex gap-2">
            <button 
              v-if="filePreview && filePreview.length > 0"
              type="submit" 
              class="btn btn-primary" 
              :disabled="uploading || !selectedAccountId"
            >
              <span v-if="uploading">
                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                {{ __('Importing...') }}
              </span>
              <span v-else>
                <i class="fa fa-upload me-1"></i>
                {{ __('Import') }} {{ filePreview.length }} {{ __('transactions') }}
              </span>
            </button>
            <button type="button" class="btn btn-secondary" @click="reset">{{ __('Reset') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MoneyHubUploadTool',
  data() {
    return {
      file: null,
      filePreview: null,
      selectedAccountId: '',
      accounts: [],
      uploading: false,
      importResult: null,
    };
  },
  computed: {
    accountGroups() {
      const groups = [...new Set(this.accounts.map(a => a.group || 'Other'))];
      return groups.sort();
    },
    accountsByGroup() {
      const grouped = {};
      this.accountGroups.forEach(group => {
        grouped[group] = this.accounts.filter(a => (a.group || 'Other') === group);
      });
      return grouped;
    },
  },
  mounted() {
    this.loadAccounts();
  },
  methods: {
    async loadAccounts() {
      try {
        const response = await fetch('/api/transaction-upload/accounts', {
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
          },
        });
        const result = await response.json();
        if (result.success) {
          this.accounts = result.accounts;
        }
      } catch (error) {
        console.error('Failed to load accounts:', error);
      }
    },
    async onFileChange(event) {
      this.file = event.target.files[0];
      this.filePreview = null;
      this.importResult = null;
      
      if (!this.file) return;

      const formData = new FormData();
      formData.append('file', this.file);

      try {
        const response = await fetch('/api/transaction-upload/moneyhub', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
          },
          body: formData,
        });

        const result = await response.json();
        if (result.success && result.preview.length > 0) {
          this.filePreview = result.preview;
        }
      } catch (error) {
        console.error('File preview failed:', error);
        alert(this.__('Failed to preview file. Please check the format.'));
      }
    },
    async onSubmit() {
      if (!this.file || !this.selectedAccountId) {
        alert(this.__('Please select an account and file'));
        return;
      }

      this.uploading = true;
      this.importResult = null;

      const formData = new FormData();
      formData.append('file', this.file);
      formData.append('account_id', this.selectedAccountId);

      try {
        const response = await fetch('/api/transaction-upload/moneyhub/import', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
          },
          body: formData,
        });

        this.importResult = await response.json();

        if (this.importResult.success) {
          // Clear file input on success
          document.getElementById('fileInput').value = '';
          this.file = null;
          this.filePreview = null;
        }
      } catch (error) {
        console.error('Import failed:', error);
        this.importResult = {
          success: false,
          message: this.__('Import failed due to a network error'),
        };
      } finally {
        this.uploading = false;
      }
    },
    reset() {
      this.file = null;
      this.filePreview = null;
      this.selectedAccountId = '';
      this.importResult = null;
      document.getElementById('fileInput').value = '';
    },
  },
};
</script>

<style scoped>
.moneyhub-upload-tool {
  max-width: 1000px;
  margin: 0 auto;
}
.card-header {
  background-color: #f8f9fa;
}
.table th, .table td {
  vertical-align: middle;
  font-size: 0.875rem;
}
.gap-2 {
  gap: 0.5rem;
}
</style>
