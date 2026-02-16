<template>
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-12 col-md-6">
        <h6 class="text-muted">{{ __('Transaction Type') }}</h6>
        <p class="mb-3">{{ draftTypeLabel || unidentifiedLabel }}</p>

        <h6 class="text-muted">{{ __('Date') }}</h6>
        <p class="mb-3">{{ formatRawValue(draftData.date) }}</p>

        <template v-if="isInvestment">
          <h6 class="text-muted">{{ __('Account') }}</h6>
          <p class="mb-3">
            {{ formatRawValue(rawData.account) }}
          </p>

          <h6 class="text-muted">{{ __('Investment') }}</h6>
          <p class="mb-3">
            {{ formatRawValue(rawData.investment) }}
          </p>
        </template>

        <template v-else>
          <template v-if="isTransfer">
            <h6 class="text-muted">{{ __('Account from') }}</h6>
            <p class="mb-3">
              {{ formatRawValue(rawData.account_from) }}
            </p>

            <h6 class="text-muted">{{ __('Account to') }}</h6>
            <p class="mb-3">
              {{ formatRawValue(rawData.account_to) }}
            </p>
          </template>

          <template v-else>
            <h6 class="text-muted">{{ __('Account') }}</h6>
            <p class="mb-3">
              {{ formatRawValue(rawData.account) }}
            </p>

            <h6 class="text-muted">{{ __('Payee') }}</h6>
            <p class="mb-3">
              {{ formatRawValue(rawData.payee) }}
            </p>
          </template>
        </template>
      </div>
      <div class="col-12 col-md-6">
        <template v-if="isInvestment">
          <h6 class="text-muted">{{ __('Amount') }}</h6>
          <p class="mb-3">
            {{ formatRawValue(rawData.amount) }}
          </p>

          <h6 class="text-muted">{{ __('Quantity') }}</h6>
          <p class="mb-3">
            {{ formatRawValue(rawData.quantity) }}
          </p>

          <h6 class="text-muted">{{ __('Price') }}</h6>
          <p class="mb-3">
            {{ formatRawValue(rawData.price) }}
          </p>

          <h6 class="text-muted">{{ __('Commission') }}</h6>
          <p class="mb-3">
            {{ formatRawValue(rawData.commission) }}
          </p>

          <h6 class="text-muted">{{ __('Tax') }}</h6>
          <p class="mb-3">
            {{ formatRawValue(rawData.tax) }}
          </p>

          <h6 class="text-muted">{{ __('Dividend') }}</h6>
          <p class="mb-3">
            {{ formatRawValue(rawData.dividend) }}
          </p>

          <h6 class="text-muted">{{ __('Currency') }}</h6>
          <p class="mb-0">
            {{ formatRawValue(rawData.currency) }}
          </p>
        </template>

        <template v-else>
          <h6 class="text-muted">{{ __('Amount') }}</h6>
          <p class="mb-3">
            {{ formatRawValue(rawData.amount) }}
          </p>

          <h6 class="text-muted">{{ __('Currency') }}</h6>
          <p class="mb-0">
            {{ formatRawValue(rawData.currency) }}
          </p>
        </template>
      </div>
    </div>

    <div v-if="hasItems" class="row">
      <div class="col-12">
        <h6 class="text-muted mb-3">{{ __('Line Items') }}</h6>
        <div class="table-responsive">
          <table class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th>{{ __('Description') }}</th>
                <th class="text-end">
                  {{ __('Amount') }}
                </th>
                <th>{{ __('Match Type') }}</th>
                <th>
                  {{ __('Category') }}
                </th>
                <th class="text-center">{{ __('Confidence') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(item, index) in draftData.transaction_items"
                :key="index"
              >
                <td>
                  {{ item.comment || item.description || __('N/A') }}
                </td>
                <td class="text-end">
                  {{ formatRawValue(item.amount) }}
                </td>
                <td>
                  <span
                    v-if="item.match_type"
                    class="badge"
                    :class="getMatchTypeBadgeClass(item.match_type)"
                  >
                    {{ getMatchTypeLabel(item.match_type) }}
                  </span>
                  <span v-else class="text-muted">{{ __('No match') }}</span>
                </td>
                <td>
                  <div
                    v-if="item.recommended_category_full_name"
                    class="d-flex align-items-center"
                  >
                    <span class="badge bg-info me-2">
                      <i class="fa fa-robot"></i>
                    </span>
                    <span class="text-muted">
                      {{ item.recommended_category_full_name }}
                    </span>
                  </div>
                  <span v-else class="text-muted">{{ unidentifiedLabel }}</span>
                </td>
                <td class="text-center">
                  <span
                    v-if="
                      item.match_type === 'ai' && item.confidence_score !== null
                    "
                    :class="getConfidenceClass(item.confidence_score)"
                  >
                    {{ formatConfidence(item.confidence_score) }}
                  </span>
                  <span
                    v-else-if="item.match_type === 'exact'"
                    class="text-muted"
                  >
                    -
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
  import { computed } from 'vue';
  import { __ } from '@/helpers';

  const unidentifiedLabel = __('Unidentified');
  const props = defineProps({
    draftData: {
      type: Object,
      default: () => ({}),
    },
    draftTypeLabel: {
      type: String,
      default: '',
    },
  });

  const hasItems = computed(
    () =>
      Array.isArray(props.draftData.transaction_items) &&
      props.draftData.transaction_items.length > 0,
  );

  const rawData = computed(() => props.draftData?.raw || {});
  const draftTransactionType = computed(
    () => rawData.value.transaction_type || props.draftData?.transaction_type,
  );
  const isInvestment = computed(
    () => props.draftData?.config_type === 'investment',
  );
  const isTransfer = computed(() => draftTransactionType.value === 'transfer');

  const formatRawValue = (value) => {
    if (value === null || value === undefined || value === '') {
      return unidentifiedLabel;
    }

    return value;
  };

  const getMatchTypeBadgeClass = (matchType) => {
    if (matchType === 'exact') {
      return 'bg-success';
    }
    if (matchType === 'ai') {
      return 'bg-primary';
    }
    return 'bg-secondary';
  };

  const getMatchTypeLabel = (matchType) => {
    if (matchType === 'exact') {
      return __('Exact Match');
    }
    if (matchType === 'ai') {
      return __('AI Suggested');
    }
    return __('No Match');
  };

  const formatConfidence = (score) => {
    if (score === null || score === undefined) {
      return '';
    }
    return `${(score * 100).toFixed(0)}%`;
  };

  const getConfidenceClass = (score) => {
    if (score === null || score === undefined) {
      return '';
    }
    if (score >= 0.8) {
      return 'text-success fw-bold';
    }
    if (score >= 0.5) {
      return 'text-warning fw-bold';
    }
    return 'text-danger fw-bold';
  };
</script>
