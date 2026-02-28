<template>
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-sm-6">
        <dl class="row mb-0">
          <dt class="col-6">{{ __('Transaction Type') }}</dt>
          <dd class="col-6" :class="{ 'text-muted': !draftTypeLabel }">
            {{ draftTypeLabel || unidentifiedLabel }}
          </dd>

          <dt class="col-6">{{ __('Date') }}</dt>
          <dd
            class="col-6"
            :class="{ 'text-muted': isUnidentified(draftData.date) }"
          >
            {{ formatRawValue(draftData.date) }}
          </dd>

          <template v-if="isInvestment">
            <dt class="col-6">{{ __('Account') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.account) }"
            >
              <template v-if="matchedEntities.account?.matched">
                <a
                  v-if="matchedEntities.account?.url"
                  :href="matchedEntities.account.url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ matchedEntities.account.name }}
                </a>
                <span v-else>{{ matchedEntities.account.name }}</span>
                <span class="text-muted small ms-1">
                  <i class="fa fa-check-circle text-success"></i>
                </span>
              </template>
              <template v-else>
                {{ formatRawValue(rawData.account) }}
              </template>
            </dd>

            <dt class="col-6">{{ __('Investment') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.investment) }"
            >
              <template v-if="matchedEntities.investment?.matched">
                <a
                  v-if="matchedEntities.investment?.url"
                  :href="matchedEntities.investment.url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ matchedEntities.investment.name }}
                </a>
                <span v-else>{{ matchedEntities.investment.name }}</span>
                <span class="text-muted small ms-1">
                  <i class="fa fa-check-circle text-success"></i>
                </span>
              </template>
              <template v-else>
                {{ formatRawValue(rawData.investment) }}
              </template>
            </dd>
          </template>

          <template v-else-if="isTransfer">
            <dt class="col-6">{{ __('Account from') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.account_from) }"
            >
              <template v-if="matchedEntities.account_from?.matched">
                <a
                  v-if="matchedEntities.account_from?.url"
                  :href="matchedEntities.account_from.url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ matchedEntities.account_from.name }}
                </a>
                <span v-else>{{ matchedEntities.account_from.name }}</span>
                <span class="text-muted small ms-1">
                  <i class="fa fa-check-circle text-success"></i>
                </span>
              </template>
              <template v-else>
                {{ formatRawValue(rawData.account_from) }}
              </template>
            </dd>

            <dt class="col-6">{{ __('Account to') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.account_to) }"
            >
              <template v-if="matchedEntities.account_to?.matched">
                <a
                  v-if="matchedEntities.account_to?.url"
                  :href="matchedEntities.account_to.url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ matchedEntities.account_to.name }}
                </a>
                <span v-else>{{ matchedEntities.account_to.name }}</span>
                <span class="text-muted small ms-1">
                  <i class="fa fa-check-circle text-success"></i>
                </span>
              </template>
              <template v-else>
                {{ formatRawValue(rawData.account_to) }}
              </template>
            </dd>
          </template>

          <template v-else>
            <dt class="col-6">{{ __('Account') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.account) }"
            >
              <template v-if="matchedEntities.account?.matched">
                <a
                  v-if="matchedEntities.account?.url"
                  :href="matchedEntities.account.url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ matchedEntities.account.name }}
                </a>
                <span v-else>{{ matchedEntities.account.name }}</span>
                <span class="text-muted small ms-1">
                  <i class="fa fa-check-circle text-success"></i>
                </span>
              </template>
              <template v-else>
                {{ formatRawValue(rawData.account) }}
              </template>
            </dd>

            <dt class="col-6">{{ __('Payee') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.payee) }"
            >
              <template v-if="matchedEntities.payee?.matched">
                <span>{{ matchedEntities.payee.name }}</span>
                <span class="text-muted small ms-1">
                  <i class="fa fa-check-circle text-success"></i>
                </span>
              </template>
              <template v-else>
                {{ formatRawValue(rawData.payee) }}
              </template>
            </dd>
          </template>
        </dl>
      </div>
      <div class="col-sm-6">
        <dl class="row mb-0">
          <dt class="col-6">{{ __('Amount') }}</dt>
          <dd
            class="col-6"
            :class="{ 'text-muted': isUnidentified(rawData.amount) }"
          >
            {{ formatRawValue(rawData.amount) }}
          </dd>

          <template v-if="isInvestment">
            <dt class="col-6">{{ __('Quantity') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.quantity) }"
            >
              {{ formatRawValue(rawData.quantity) }}
            </dd>

            <dt class="col-6">{{ __('Price') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.price) }"
            >
              {{ formatRawValue(rawData.price) }}
            </dd>

            <dt class="col-6">{{ __('Commission') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.commission) }"
            >
              {{ formatRawValue(rawData.commission) }}
            </dd>

            <dt class="col-6">{{ __('Tax') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.tax) }"
            >
              {{ formatRawValue(rawData.tax) }}
            </dd>

            <dt class="col-6">{{ __('Dividend') }}</dt>
            <dd
              class="col-6"
              :class="{ 'text-muted': isUnidentified(rawData.dividend) }"
            >
              {{ formatRawValue(rawData.dividend) }}
            </dd>
          </template>

          <dt class="col-6">{{ __('Currency') }}</dt>
          <dd
            class="col-6"
            :class="{ 'text-muted': isUnidentified(rawData.currency) }"
          >
            {{ formatRawValue(rawData.currency) }}
          </dd>
        </dl>
      </div>
    </div>

    <div v-if="hasItems" class="row">
      <div class="col-12">
        <h6 class="text-muted mb-3">{{ __('Line items') }}</h6>
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
  import { __ } from '@/i18n';

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
  const matchedEntities = computed(
    () => props.draftData?.matched_entities || {},
  );
  const draftTransactionType = computed(
    () => rawData.value.transaction_type || props.draftData?.transaction_type,
  );
  const isInvestment = computed(
    () => props.draftData?.config_type === 'investment',
  );
  const isTransfer = computed(() => draftTransactionType.value === 'transfer');

  const isUnidentified = (value) => {
    return value === null || value === undefined || value === '';
  };

  const formatRawValue = (value) => {
    if (isUnidentified(value)) {
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
      return __('Exact match');
    }
    if (matchType === 'ai') {
      return __('AI suggested');
    }
    return __('No match');
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
