<template>
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label" for="import-source-type">
            {{ __('Import source') }}
          </label>
          <div id="import-source-type" class="d-flex gap-3">
            <div class="form-check">
              <input
                id="source-qif"
                class="form-check-input"
                type="radio"
                name="source_type"
                value="qif"
                :checked="modelValue === 'qif'"
                @change="emitSourceType('qif')"
              />
              <label class="form-check-label" for="source-qif">
                {{ __('QIF') }}
              </label>
            </div>
            <div class="form-check">
              <input
                id="source-csv"
                class="form-check-input"
                type="radio"
                name="source_type"
                value="csv"
                :checked="modelValue === 'csv'"
                @change="emitSourceType('csv')"
              />
              <label class="form-check-label" for="source-csv">
                {{ __('CSV') }}
              </label>
            </div>
          </div>
          <div v-if="modelValue === 'csv'" class="form-text text-warning mt-2">
            {{
              __(
                'CSV backend parsing will be enabled in the next milestone. Please use QIF for now.',
              )
            }}
          </div>
        </div>

        <div class="col-lg-8">
          <label class="form-label" for="target-account">
            {{ __('Target account') }}
          </label>
          <select
            id="target-account"
            class="form-select"
            :value="selectedAccountId"
            :disabled="loadingAccounts"
            @change="emitAccountId($event.target.value)"
          >
            <option value="">
              {{
                loadingAccounts
                  ? __('Loading accounts...')
                  : __('Select account')
              }}
            </option>
            <option
              v-for="account in accounts"
              :key="account.id"
              :value="String(account.id)"
            >
              {{ account.name }}
            </option>
          </select>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  export default {
    name: 'ImportSourceSelector',
    props: {
      modelValue: {
        type: String,
        required: true,
      },
      accounts: {
        type: Array,
        required: true,
      },
      selectedAccountId: {
        type: String,
        required: true,
      },
      loadingAccounts: {
        type: Boolean,
        default: false,
      },
    },
    emits: ['update:modelValue', 'update:selectedAccountId'],
    methods: {
      emitSourceType(sourceType) {
        this.$emit('update:modelValue', sourceType);
      },
      emitAccountId(accountId) {
        this.$emit('update:selectedAccountId', accountId);
      },
    },
  };
</script>
