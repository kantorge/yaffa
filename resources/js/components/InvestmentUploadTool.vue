<template>
  <div class="investment-upload-tool">
    <h5>{{ __('Upload Investment Transactions') }}</h5>
    
    <div class="card">
      <div class="card-header">
        <h6>{{ __('Configuration & Upload') }}</h6>
      </div>
      <div class="card-body">
        <form @submit.prevent="onSubmit">
          <div class="mb-3">
            <label for="sourceSelect" class="form-label">{{ __('Select Source') }}</label>
            <select v-model="selectedSource" id="sourceSelect" class="form-select" required @change="onSourceChange">
              <option disabled value="">-- {{ __('Choose Source') }} --</option>
              <option value="WiseAlpha">WiseAlpha</option>
              <option value="Trading212">Trading 212</option>
              <option value="MoneyHub">MoneyHub</option>
              <option value="CompanyJSON">Custom JSON/YAML</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label for="defaultAccount" class="form-label">{{ __('Default Account') }}</label>
            <select v-model="defaultAccountId" id="defaultAccount" class="form-select" required>
              <option disabled value="">-- {{ __('Choose Account') }} --</option>
              <option v-for="account in accounts" :key="account.id" :value="account.id">
                {{ account.name }}
              </option>
            </select>
            <div class="form-text">
              {{ __('This account will be used for transactions that don\'t specify an account') }}
            </div>
          </div>
          
          <div class="mb-3">
            <label for="fileInput" class="form-label">{{ __('File (CSV, XLSX, JSON, or YAML)') }}</label>
            <input 
              type="file" 
              id="fileInput" 
              class="form-control" 
              accept=".csv,.xlsx,.json,.yaml,.yml" 
              @change="onFileChange" 
              required 
            />
            <div class="form-text">
              {{ __('Supported formats: CSV, Excel, JSON, YAML. Max size: 10MB') }}
            </div>
          </div>
          
          <!-- File Preview -->
          <div v-if="filePreview && filePreview.headers.length > 0" class="mb-3">
            <h6>{{ __('File Preview') }}</h6>
            <div class="table-responsive">
              <table class="table table-sm table-bordered">
                <thead>
                  <tr>
                    <th v-for="header in filePreview.headers" :key="header">{{ header }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, index) in filePreview.rows" :key="index">
                    <td v-for="header in filePreview.headers" :key="header">
                      {{ row[header] }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Duplicate Detection Settings -->
          <div class="mb-3">
            <div class="form-check">
              <input 
                class="form-check-input" 
                type="checkbox" 
                v-model="skipDuplicates" 
                id="skipDuplicates"
              >
              <label class="form-check-label" for="skipDuplicates">
                {{ __('Skip duplicate transactions') }}
              </label>
            </div>
            <div class="form-text">
              {{ __('Checks for existing transactions with same date, investment, account, quantity, and price (±0.01 tolerance)') }}
            </div>
          </div>
          
          <!-- Advanced Mapping (for custom sources) -->
          <div v-if="selectedSource === 'CompanyJSON' && fieldMapping" class="mb-3">
            <h6>{{ __('Field Mapping') }}</h6>
            <div class="alert alert-info">
              {{ __('Configure how fields in your file map to YAFFA transaction fields') }}
            </div>
            <div v-for="(mapping, field) in fieldMapping" :key="field" class="row mb-2">
              <div class="col-md-4">
                <label :for="'mapping_' + field" class="form-label">{{ field }}</label>
              </div>
              <div class="col-md-4">
                <select :id="'mapping_' + field" v-model="mapping.target" class="form-select form-select-sm">
                  <option value="">-- {{ __('Skip Field') }} --</option>
                  <option value="date">{{ __('Date') }}</option>
                  <option value="_transaction_type_name">{{ __('Transaction Type') }}</option>
                  <option value="_symbol">{{ __('Investment Symbol/ISIN') }}</option>
                  <option value="_account_name">{{ __('Account Name') }}</option>
                  <option value="config.quantity">{{ __('Quantity') }}</option>
                  <option value="config.price">{{ __('Price') }}</option>
                  <option value="config.commission">{{ __('Commission') }}</option>
                  <option value="config.tax">{{ __('Tax') }}</option>
                  <option value="config.dividend">{{ __('Dividend') }}</option>
                  <option value="comment">{{ __('Comment') }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <select v-model="mapping.transform" class="form-select form-select-sm">
                  <option value="">{{ __('No Transform') }}</option>
                  <option value="date">{{ __('Parse as Date') }}</option>
                  <option value="float">{{ __('Parse as Number') }}</option>
                  <option value="divide_by_100">{{ __('Divide by 100') }}</option>
                  <option value="multiply_by_100">{{ __('Multiply by 100') }}</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" @click="previewFile" :disabled="!selectedSource || !file">
              {{ __('Preview') }}
            </button>
            <button type="submit" class="btn btn-primary" :disabled="uploading || !selectedSource || !file">
              <span v-if="uploading" class="spinner-border spinner-border-sm me-2" role="status"></span>
              {{ uploading ? __('Processing...') : __('Upload & Process') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Import Progress -->
    <div v-if="uploading && importStatus" class="mt-3">
      <div class="card border-info">
        <div class="card-header bg-info text-white">
          <h6 class="mb-0">{{ __('Processing Import') }}</h6>
        </div>
        <div class="card-body">
          <div class="mb-2">
            <strong>{{ __('Status') }}:</strong> {{ importStatus.status }}
          </div>
          <div class="mb-2">
            <strong>{{ __('Processed') }}:</strong> {{ importStatus.processed_rows }} {{ __('rows') }}
          </div>
          <div class="progress">
            <div 
              class="progress-bar progress-bar-striped progress-bar-animated" 
              role="progressbar" 
              :style="{ width: importStatus.total_rows ? ((importStatus.processed_rows / importStatus.total_rows) * 100) + '%' : '100%' }"
            ></div>
          </div>
          <div class="mt-2 text-muted small">
            {{ __('This may take a few moments for large files...') }}
          </div>
        </div>
      </div>
    </div>

    <!-- Results -->
    <div v-if="uploadResult" class="mt-3">
      <div class="card">
        <div class="card-header" :class="uploadResult.success ? 'bg-success text-white' : 'bg-danger text-white'">
          <h6 class="mb-0">{{ __('Upload Results') }}</h6>
        </div>
        <div class="card-body">
          <div v-if="uploadResult.success">
            <div class="row">
              <div class="col-md-3">
                <div class="text-center">
                  <div class="fs-4 fw-bold text-success">{{ uploadResult.results.processed }}</div>
                  <div class="text-muted">{{ __('Processed') }}</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="text-center">
                  <div class="fs-4 fw-bold">{{ uploadResult.results.total }}</div>
                  <div class="text-muted">{{ __('Total Rows') }}</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="text-center">
                  <div class="fs-4 fw-bold text-warning">{{ uploadResult.results.duplicates }}</div>
                  <div class="text-muted">{{ __('Duplicates') }}</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="text-center">
                  <div class="fs-4 fw-bold text-danger">{{ uploadResult.results.errors.length }}</div>
                  <div class="text-muted">{{ __('Errors') }}</div>
                </div>
              </div>
            </div>
            
            <div v-if="uploadResult.results.errors.length > 0" class="mt-3">
              <h6>{{ __('Errors') }}</h6>
              <div class="alert alert-danger">
                <ul class="mb-0">
                  <li v-for="error in uploadResult.results.errors" :key="error">{{ error }}</li>
                </ul>
              </div>
            </div>
          </div>
          <div v-else class="alert alert-danger">
            {{ uploadResult.message }}
          </div>
        </div>
      </div>
    </div>

    <!-- Help Section -->
    <div class="mt-3">
      <div class="card">
        <div class="card-header">
          <h6>{{ __('Help & Examples') }}</h6>
        </div>
        <div class="card-body">
          <div class="accordion" id="helpAccordion">
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#yamlExample">
                  {{ __('YAML Format Example') }}
                </button>
              </h2>
              <div id="yamlExample" class="accordion-collapse collapse">
                <div class="accordion-body">
                  <pre><code>transactions:
  - date: "2024-01-15"
    type: "Buy"
    bond_name: "XS2811958839"
    account: "WiseAlpha GBP"
    quantity: 100
    price: 9950  # Will be divided by 100
    commission: 5.00
    description: "Bond purchase"
    
mapping:
  date:
    target: "date"
    transform: "date"
  type:
    target: "_transaction_type_name"
  bond_name:
    target: "_symbol"
  price:
    target: "config.price"
    transform: "divide_by_100"</code></pre>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'InvestmentUploadTool',
  data() {
    return {
      selectedSource: '',
      defaultAccountId: '',
      accounts: [],
      file: null,
      filePreview: null,
      fieldMapping: null,
      skipDuplicates: true,
      uploading: false,
      uploadResult: null,
      previewRows: [],
      previewLoading: false,
      previewError: null,
      importStatus: null,
      importId: null,
      pollInterval: null,
    };
  },
  mounted() {
    this.loadAccounts();
  },
  methods: {
    async loadAccounts() {
      try {
        const response = await fetch('/api/accounts');
        const data = await response.json();
        this.accounts = data;
      } catch (error) {
        console.error('Failed to load accounts:', error);
      }
    },
    
    onSourceChange() {
      this.filePreview = null;
      this.fieldMapping = null;
      this.uploadResult = null;
    },
    
    async onFileChange(event) {
      this.file = event.target.files[0];
      if (!this.file) return;
      this.previewLoading = true;
      this.previewError = null;
      this.previewRows = [];
      const formData = new FormData();
      formData.append('file', this.file);
      formData.append('source', this.selectedSource);
      try {
        const response = await fetch('/api/investment-upload/preview', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
          },
          body: formData,
        });
        const result = await response.json();
        if (result.success) {
          this.previewRows = result.preview;
        } else {
          this.previewError = result.message || 'Preview failed.';
        }
      } catch (e) {
        this.previewError = e.message;
      } finally {
        this.previewLoading = false;
      }
    },
    
    async previewFile() {
      if (!this.selectedSource || !this.file) return;
      
      const formData = new FormData();
      formData.append('source', this.selectedSource);
      formData.append('file', this.file);
      
      try {
        const response = await fetch('/api/investment-upload/validate', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
          },
          body: formData,
        });
        
        const result = await response.json();
        
        if (result.success) {
          this.filePreview = result.preview;
          
          // Setup field mapping for custom sources
          if (this.selectedSource === 'CompanyJSON' && this.filePreview.headers.length > 0) {
            this.fieldMapping = {};
            this.filePreview.headers.forEach(header => {
              this.fieldMapping[header] = { target: '', transform: '' };
            });
          }
        } else {
          this.uploadResult = result;
        }
      } catch (e) {
        this.uploadResult = { success: false, message: e.message };
      }
    },
    
    async onSubmit() {
      if (!this.selectedSource || !this.file || !this.defaultAccountId) return;
      
      this.uploading = true;
      this.uploadResult = null;
      this.importStatus = null;
      
      const formData = new FormData();
      formData.append('source', this.selectedSource);
      formData.append('file', this.file);
      formData.append('default_account_id', this.defaultAccountId);
      
      if (this.selectedSource === 'CompanyJSON' && this.fieldMapping) {
        formData.append('mapping', JSON.stringify(this.fieldMapping));
      }
      
      try {
        const response = await fetch('/api/investment-upload', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
          },
          body: formData,
        });
        
        const result = await response.json();
        
        if (result.success && result.queued) {
          // Background processing - start polling for status
          this.importId = result.import_id;
          this.importStatus = { status: 'queued', processed_rows: 0 };
          this.startPolling();
        } else {
          // Direct response (old behavior for small files or errors)
          this.uploadResult = result;
          this.uploading = false;
        }
        
        if (result.success) {
          // Clear form after successful upload
          this.selectedSource = '';
          this.defaultAccountId = '';
          this.file = null;
          this.filePreview = null;
          this.fieldMapping = null;
          this.skipDuplicates = true;
        }
      } catch (e) {
        this.uploadResult = { success: false, message: e.message };
        this.uploading = false;
      }
    },
    
    startPolling() {
      this.pollInterval = setInterval(() => {
        this.checkImportStatus();
      }, 2000); // Poll every 2 seconds
    },
    
    stopPolling() {
      if (this.pollInterval) {
        clearInterval(this.pollInterval);
        this.pollInterval = null;
      }
    },
    
    async checkImportStatus() {
      if (!this.importId) return;
      
      try {
        const response = await fetch(`/imports/${this.importId}/status`, {
          headers: {
            'Accept': 'application/json',
          },
        });
        
        const status = await response.json();
        this.importStatus = status;
        
        if (status.status === 'finished') {
          this.stopPolling();
          this.uploading = false;
          this.uploadResult = {
            success: true,
            message: `Import completed! Processed ${status.processed_rows} rows.`,
            results: {
              processed: status.processed_rows,
              total: status.total_rows || status.processed_rows,
              duplicates: 0,
              errors: status.errors || [],
            }
          };
        } else if (status.status === 'failed') {
          this.stopPolling();
          this.uploading = false;
          this.uploadResult = {
            success: false,
            message: 'Import failed: ' + (status.errors ? status.errors.join(', ') : 'Unknown error'),
          };
        }
      } catch (e) {
        console.error('Failed to check import status:', e);
      }
    },
  },
  
  beforeUnmount() {
    this.stopPolling();
  },
};
</script>

<style scoped>
.investment-upload-tool {
  max-width: 800px;
  margin: 0 auto;
}

.card-header {
  background-color: #f8f9fa;
}

.table th, .table td {
  vertical-align: middle;
}

.spinner-border {
  width: 1.2rem;
  height: 1.2rem;
}
</style>