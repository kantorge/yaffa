<template>
  <div class="card mb-4" id="widgetManualAssetOverview">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="card-title">
        {{ __('Manual allocation & trends') }}
      </div>
      <div v-if="state === 'data-available'">
        {{ toFormattedCurrency(totalBase, locale, baseCurrency) }}
      </div>
    </div>

    <div class="card-body" v-if="state === 'loading'">
      <div class="placeholder-glow">
        <div class="placeholder col-12 mb-2"></div>
        <div class="placeholder col-10 mb-2"></div>
        <div class="placeholder col-8"></div>
      </div>
    </div>

    <div class="card-body" v-else-if="state === 'error'">
      <div class="alert alert-danger mb-0">
        {{ __('There was an error while loading manual assets: ') }}
        {{ errorMessage }}
      </div>
    </div>

    <div class="card-body" v-else>
      <div class="d-flex flex-wrap gap-3 mb-3">
        <label class="form-check">
          <input
            class="form-check-input"
            type="checkbox"
            v-model="filters.showAccounts"
            @change="persistFilters"
          />
          <span class="form-check-label">{{ __('Accounts') }}</span>
        </label>
        <label class="form-check">
          <input
            class="form-check-input"
            type="checkbox"
            v-model="filters.showInvestments"
            @change="persistFilters"
          />
          <span class="form-check-label">{{ __('Investments') }}</span>
        </label>
        <label class="form-check">
          <input
            class="form-check-input"
            type="checkbox"
            v-model="filters.showInactive"
            @change="persistFilters"
          />
          <span class="form-check-label">{{ __('Include inactive') }}</span>
        </label>
      </div>

      <div v-if="filteredAssets.length === 0" class="alert alert-info mb-0">
        {{ __('No manual balances yet. Add manual balances or trends to accounts or investments to see them here.') }}
      </div>

      <div v-else class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>{{ __('Instrument') }}</th>
              <th>{{ __('Group') }}</th>
              <th class="text-end">{{ __('Allocation') }}</th>
              <th class="text-end">{{ __('Manual balance') }}</th>
              <th class="text-end">{{ __('Trend') }}</th>
              <th class="text-end">{{ __('Interest rate') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="asset in filteredAssets" :key="assetKey(asset)">
              <td>
                <div class="fw-semibold">{{ asset.name }}</div>
                <div class="text-muted small">
                  {{ asset.type === 'account' ? __('Account') : __('Investment') }}
                </div>
              </td>
              <td>{{ asset.group_name }}</td>
              <td class="text-end">
                <div class="small text-muted">
                  {{ formatPercent(assetAllocation(asset)) }}
                </div>
                <div class="progress" style="height: 6px;">
                  <div
                    class="progress-bar"
                    role="progressbar"
                    :style="{ width: assetAllocation(asset) + '%' }"
                    :aria-valuenow="assetAllocation(asset)"
                    aria-valuemin="0"
                    aria-valuemax="100"
                  ></div>
                </div>
              </td>
              <td class="text-end">
                <div>
                  {{ toFormattedCurrency(asset.balance, locale, asset.currency) }}
                </div>
                <div class="text-muted small" v-if="asset.currency.id !== baseCurrency.id">
                  {{ toFormattedCurrency(asset.balance_base, locale, baseCurrency) }}
                </div>
              </td>
              <td class="text-end">
                <span :class="trendClass(asset.trend)">
                  {{ formatTrend(asset.trend) }}
                </span>
              </td>
              <td class="text-end">
                <span :class="trendClass(asset.interest_rate)">
                  {{ formatRate(asset.interest_rate) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency } from '@/helpers';
  import * as toastHelpers from '@/toast';

  const FILTERS_STORAGE_KEY = 'yaffa-dashboard-manual-asset-filters';

  export default {
    props: {
      locale: {
        type: String,
        default: window.YAFFA.locale,
      },
    },

    data() {
      return {
        state: 'loading',
        errorMessage: null,
        assets: [],
        baseCurrency: window.YAFFA.baseCurrency,
        filters: {
          showAccounts: true,
          showInvestments: true,
          showInactive: false,
        },
      };
    },

    computed: {
      filteredAssets() {
        return this.assets
          .filter((asset) => {
            if (!this.filters.showInactive && !asset.active) {
              return false;
            }

            if (asset.type === 'account' && !this.filters.showAccounts) {
              return false;
            }

            if (asset.type === 'investment' && !this.filters.showInvestments) {
              return false;
            }

            return true;
          })
          .sort((a, b) => b.balance_base - a.balance_base);
      },

      totalBase() {
        return this.filteredAssets.reduce(
          (total, asset) => total + asset.balance_base,
          0,
        );
      },
    },

    created() {
      this.restoreFilters();
      this.fetchManualAssets();
    },

    methods: {
      fetchManualAssets() {
        this.state = 'loading';

        axios
          .get('/api/dashboard/manual-assets')
          .then((response) => {
            if (response.data.result !== 'success') {
              this.state = 'error';
              this.errorMessage = response.data.message || __('Unknown error');
              return;
            }

            this.assets = response.data.assets || [];
            this.baseCurrency = response.data.base_currency || this.baseCurrency;
            this.state = 'data-available';
          })
          .catch((error) => {
            this.state = 'error';
            this.errorMessage = error.message;
            toastHelpers.showErrorToast(error.message);
          });
      },

      assetKey(asset) {
        return `${asset.type}-${asset.id}`;
      },

      assetAllocation(asset) {
        if (!this.totalBase) {
          return 0;
        }

        return Math.max(
          0,
          Math.min(100, (asset.balance_base / this.totalBase) * 100),
        );
      },

      formatTrend(value) {
        if (value === null || value === undefined || value === '') {
          return '—';
        }

        return `${Number(value).toFixed(2)}%`;
      },

      formatRate(value) {
        if (value === null || value === undefined || value === '') {
          return '—';
        }

        return `${Number(value).toFixed(2)}%`;
      },

      formatPercent(value) {
        return `${Number(value).toFixed(1)}%`;
      },

      trendClass(value) {
        if (value === null || value === undefined || value === '') {
          return 'text-muted';
        }

        if (Number(value) > 0) {
          return 'text-success';
        }

        if (Number(value) < 0) {
          return 'text-danger';
        }

        return 'text-muted';
      },

      persistFilters() {
        localStorage.setItem(FILTERS_STORAGE_KEY, JSON.stringify(this.filters));
      },

      restoreFilters() {
        const stored = localStorage.getItem(FILTERS_STORAGE_KEY);
        if (!stored) {
          return;
        }

        try {
          const parsed = JSON.parse(stored);
          this.filters = {
            ...this.filters,
            ...parsed,
          };
        } catch (error) {
          localStorage.removeItem(FILTERS_STORAGE_KEY);
        }
      },

      toFormattedCurrency,
      __,
    },
  };
</script>
