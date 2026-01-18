<template>
    <div>
        <!-- Modal -->
        <div class="modal fade" id="balanceCheckpointModal" tabindex="-1" aria-labelledby="balanceCheckpointModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="balanceCheckpointModalLabel">{{ __('Balance Checkpoints') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Add New Checkpoint Form -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">{{ __('Add Balance Checkpoint') }}</h6>
                            </div>
                            <div class="card-body">
                                <form @submit.prevent="createCheckpoint">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label for="checkpoint_date" class="form-label">{{ __('Date') }}</label>
                                            <input 
                                                type="date" 
                                                class="form-control" 
                                                id="checkpoint_date"
                                                v-model="newCheckpoint.date"
                                                required
                                            >
                                        </div>
                                        <div class="col-md-4">
                                            <label for="checkpoint_balance" class="form-label">{{ __('Balance') }}</label>
                                            <input 
                                                type="number" 
                                                step="0.01" 
                                                class="form-control" 
                                                id="checkpoint_balance"
                                                v-model.number="newCheckpoint.balance"
                                                required
                                            >
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-success w-100" :disabled="creating">
                                                <i class="fa fa-plus"></i> {{ creating ? __('Creating...') : __('Add Checkpoint') }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <label for="checkpoint_note" class="form-label">{{ __('Note (optional)') }}</label>
                                            <input 
                                                type="text" 
                                                class="form-control" 
                                                id="checkpoint_note"
                                                v-model="newCheckpoint.note"
                                                maxlength="500"
                                                placeholder="e.g., Bank statement balance"
                                            >
                                        </div>
                                    </div>
                                    <div v-if="createError" class="alert alert-danger mt-2 mb-0">
                                        {{ createError }}
                                    </div>
                                    <div v-if="createSuccess" class="alert alert-success mt-2 mb-0">
                                        {{ createSuccess }}
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Existing Checkpoints List -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">{{ __('Existing Checkpoints') }}</h6>
                            </div>
                            <div class="card-body">
                                <div v-if="loading" class="text-center py-3">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">{{ __('Loading...') }}</span>
                                    </div>
                                </div>
                                <div v-else-if="checkpoints.length === 0" class="text-muted text-center py-3">
                                    {{ __('No checkpoints created yet') }}
                                </div>
                                <div v-else class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Date') }}</th>
                                                <th>{{ __('Balance') }}</th>
                                                <th>{{ __('Note') }}</th>
                                                <th>{{ __('Status') }}</th>
                                                <th class="text-center">{{ __('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="checkpoint in checkpoints" :key="checkpoint.id">
                                                <td>{{ formatDate(checkpoint.checkpoint_date) }}</td>
                                                <td>{{ formatCurrency(checkpoint.balance) }}</td>
                                                <td>
                                                    <small class="text-muted">{{ checkpoint.note || '-' }}</small>
                                                </td>
                                                <td>
                                                    <span v-if="checkpoint.matches" class="badge bg-success">
                                                        <i class="fa fa-check-circle"></i> {{ __('Matched') }}
                                                    </span>
                                                    <span v-else-if="checkpoint.status === 'not matched'" class="badge bg-warning text-dark">
                                                        <i class="fa fa-exclamation-triangle"></i> {{ __('Not Matched') }}
                                                    </span>
                                                    <span v-else-if="checkpoint.active" class="badge bg-success">
                                                        <i class="fa fa-check"></i> {{ __('Active') }}
                                                    </span>
                                                    <span v-else class="badge bg-secondary">
                                                        {{ __('Inactive') }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button 
                                                        v-if="checkpoint.active"
                                                        @click="checkIntegrity(checkpoint)"
                                                        class="btn btn-sm btn-info"
                                                        :disabled="checking"
                                                        :title="__('Check if balance matches')"
                                                    >
                                                        <i class="fa fa-calculator"></i>
                                                    </button>
                                                    <button 
                                                        v-if="checkpoint.active"
                                                        @click="deactivateCheckpoint(checkpoint)"
                                                        class="btn btn-sm btn-danger"
                                                        :title="__('Deactivate')"
                                                    >
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Integrity Check Result -->
                        <div v-if="integrityResult" class="alert mt-3" :class="integrityResult.matches ? 'alert-success' : 'alert-warning'">
                            <h6>
                                <i :class="integrityResult.matches ? 'fa fa-check-circle' : 'fa fa-exclamation-triangle'"></i>
                                {{ __('Balance Integrity Check') }}
                            </h6>
                            <p class="mb-0">
                                <strong>{{ __('Checkpoint Balance:') }}</strong> {{ formatCurrency(integrityResult.checkpoint_balance) }}<br>
                                <strong>{{ __('Calculated Balance:') }}</strong> {{ formatCurrency(integrityResult.calculated_balance) }}<br>
                                <strong>{{ __('Status:') }}</strong> 
                                <span v-if="integrityResult.matches" class="text-success">
                                    {{ __('✓ Balances match!') }}
                                </span>
                                <span v-else class="text-warning">
                                    {{ __('⚠ Balances do not match. Difference: ') }} {{ formatCurrency(Math.abs(integrityResult.calculated_balance - integrityResult.checkpoint_balance)) }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'BalanceCheckpointModal',
    props: {
        accountEntityId: {
            type: Number,
            required: true
        },
        currentBalance: {
            type: Number,
            default: null
        },
        currencyCode: {
            type: String,
            default: 'USD'
        }
    },
    data() {
        return {
            checkpoints: [],
            loading: false,
            creating: false,
            checking: false,
            createError: null,
            createSuccess: null,
            integrityResult: null,
            newCheckpoint: {
                date: new Date().toISOString().split('T')[0],
                balance: this.currentBalance || 0,
                note: ''
            }
        };
    },
    mounted() {
        // Load checkpoints when modal is shown
        const modalEl = document.getElementById('balanceCheckpointModal');
        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', () => {
                this.loadCheckpoints();
            });
        }
    },
    methods: {
        async loadCheckpoints() {
            this.loading = true;
            try {
                const response = await axios.get('/api/balance-checkpoints', {
                    params: {
                        account_entity_id: this.accountEntityId
                    }
                });
                this.checkpoints = response.data;
            } catch (error) {
                console.error('Error loading checkpoints:', error);
            } finally {
                this.loading = false;
            }
        },
        async createCheckpoint() {
            this.creating = true;
            this.createError = null;
            this.createSuccess = null;
            this.integrityResult = null;

            try {
                const response = await axios.post('/api/balance-checkpoints', {
                    account_entity_id: this.accountEntityId,
                    checkpoint_date: this.newCheckpoint.date,
                    balance: this.newCheckpoint.balance,
                    note: this.newCheckpoint.note || null
                });

                this.createSuccess = this.__('Checkpoint created successfully!');
                
                // Reset form
                this.newCheckpoint.date = new Date().toISOString().split('T')[0];
                this.newCheckpoint.note = '';
                // Keep balance for convenience
                
                // Reload checkpoints
                await this.loadCheckpoints();

                // Clear success message after 3 seconds
                setTimeout(() => {
                    this.createSuccess = null;
                }, 3000);

            } catch (error) {
                console.error('Error creating checkpoint:', error);
                if (error.response && error.response.data && error.response.data.errors) {
                    const errors = Object.values(error.response.data.errors).flat();
                    this.createError = errors.join(' ');
                } else {
                    this.createError = this.__('Failed to create checkpoint. Please try again.');
                }
            } finally {
                this.creating = false;
            }
        },
        async deactivateCheckpoint(checkpoint) {
            if (!confirm(this.__('Are you sure you want to deactivate this checkpoint?'))) {
                return;
            }

            try {
                await axios.delete(`/api/balance-checkpoints/${checkpoint.id}`);
                await this.loadCheckpoints();
                this.integrityResult = null;
            } catch (error) {
                console.error('Error deactivating checkpoint:', error);
                alert(this.__('Failed to deactivate checkpoint. Please try again.'));
            }
        },
        async checkIntegrity(checkpoint) {
            this.checking = true;
            this.integrityResult = null;

            try {
                const response = await axios.post('/api/balance-checkpoints/check-integrity', {
                    account_entity_id: this.accountEntityId,
                    checkpoint_date: checkpoint.checkpoint_date
                });

                this.integrityResult = response.data;
            } catch (error) {
                console.error('Error checking integrity:', error);
                alert(this.__('Failed to check balance integrity. Please try again.'));
            } finally {
                this.checking = false;
            }
        },
        formatDate(date) {
            return new Date(date).toLocaleDateString(window.YAFFA?.locale || 'en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },
        formatCurrency(amount) {
            return new Intl.NumberFormat(window.YAFFA?.locale || 'en-US', {
                style: 'currency',
                currency: this.currencyCode,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        },
        __(key, replace = {}) {
            return window.__(key, replace);
        }
    }
};
</script>

<style scoped>
.table {
    font-size: 0.9rem;
}

.badge {
    font-size: 0.75rem;
}
</style>
