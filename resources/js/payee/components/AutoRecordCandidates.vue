<template>
  <div>
    <!-- ponytail: static until the auto_record_enabled switch exists; show it only while the switch is off then -->
    <div class="alert alert-info">
      <i class="fa fa-circle-info me-1"></i>
      {{
        __(
          'Nothing is recorded automatically yet. This page shows which payees would qualify, based on their transaction history and your thresholds.',
        )
      }}
      <a :href="route('user.ai-settings')">{{ __('Adjust the thresholds') }}</a>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
          <li v-for="tab in tabs" :key="tab.key" class="nav-item">
            <button
              type="button"
              class="nav-link"
              :class="{ active: activeTab === tab.key }"
              @click="activeTab = tab.key"
            >
              {{ tab.label }}
              <span class="badge bg-secondary ms-1">
                {{ filtered(tab.key).length }}
              </span>
            </button>
          </li>
        </ul>
      </div>
      <div class="card-body">
        <p class="text-muted">{{ activeDescription }}</p>

        <div v-if="loading" class="text-muted">
          <i class="fa fa-spinner fa-spin"></i> {{ __('Loading...') }}
        </div>
        <div v-else-if="error" class="alert alert-danger mb-0">
          {{ __('Unable to load the candidates.') }}
        </div>
        <p v-else-if="rows.length === 0" class="mb-0">
          {{ __('No payees in this group.') }}
        </p>
        <div v-else class="table-responsive">
          <table class="table table-striped table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>{{ __('Payee') }}</th>
                <th class="text-end">{{ __('Transactions') }}</th>
                <th>{{ __('Usual category') }}</th>
                <th class="text-end">{{ __('Consistency') }}</th>
                <th class="text-end">{{ __('Usual amount') }}</th>
                <th>{{ __('Why') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.payee_id">
                <td>
                  <a
                    :href="
                      route('account-entity.show', {
                        account_entity: row.payee_id,
                      })
                    "
                    >{{ row.name }}</a
                  >
                  <span
                    v-if="row.auto_record_policy !== 'follow_global'"
                    class="badge bg-info ms-1"
                  >
                    {{
                      row.auto_record_policy === 'always'
                        ? __('Always allow')
                        : __('Never')
                    }}
                  </span>
                  <span
                    v-if="row.itemization_expected"
                    class="badge bg-info ms-1"
                  >
                    {{ __('Itemized') }}
                  </span>
                </td>
                <td class="text-end">{{ row.sample_size }}</td>
                <td>
                  <template v-if="row.dominant_category">
                    {{ row.dominant_category }}
                    <span class="text-muted"
                      >({{ formatPercent(row.dominant_share) }})</span
                    >
                  </template>
                </td>
                <td class="text-end">{{ formatNumber(row.wilson_lower) }}</td>
                <td class="text-end">
                  {{ formatMoney(row.amount_median, row.currency) }}
                </td>
                <td>{{ row.reason }}</td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    :title="__('Edit payee')"
                    @click="$refs.payeeForm.show(row.payee_id)"
                  >
                    <i class="fa fa-edit"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <payee-form
      id="editPayeeModal"
      ref="payeeForm"
      action="edit"
      @payee-selected="load"
    ></payee-form>
  </div>
</template>

<script>
  import {
    __,
    toFormattedCurrency,
    toFormattedNumber,
  } from '@/shared/lib/i18n';
  import PayeeForm from './PayeeForm.vue';

  export default {
    name: 'AutoRecordCandidates',
    components: { PayeeForm },
    data() {
      return {
        groups: {},
        activeTab: 'qualifying',
        loading: true,
        error: false,
        locale: window.YAFFA.userSettings.locale,
        // yes / any / no per boolean row property, driven by the sidebar switches
        filters: { active: 'any', has_active_schedule: 'any' },
      };
    },
    computed: {
      tabs() {
        return [
          {
            key: 'qualifying',
            label: __('Qualifying'),
            description: __(
              'These payees pass the history check. With auto-recording switched on, their documents would be recorded without review.',
            ),
          },
          {
            key: 'near',
            label: __('Almost there'),
            description: __(
              'These payees are consistent, but need a few more transactions before they qualify.',
            ),
          },
          {
            key: 'itemization_mismatch',
            label: __('Itemization mismatch'),
            description: __(
              'The itemization setting of these payees does not match how you record them.',
            ),
          },
          {
            key: 'template_candidates',
            label: __('Template candidates'),
            description: __(
              'You record the same transaction here again and again. A template would save the typing.',
            ),
          },
        ];
      },
      activeDescription() {
        return this.tabs.find((tab) => tab.key === this.activeTab).description;
      },
      rows() {
        return this.filtered(this.activeTab);
      },
    },
    mounted() {
      this.initializeFilters();
      this.load();
    },
    methods: {
      // The switches are the shared sidebar component; their radio ids end with yes / any / no
      initializeFilters() {
        Object.keys(this.filters).forEach((property) => {
          const radios = document.querySelectorAll(
            `input[name="table_filter_${property}"]`,
          );
          const read = (radio) => radio.id.split('_').pop();

          radios.forEach((radio) => {
            if (radio.checked) {
              this.filters[property] = read(radio);
            }
            radio.addEventListener('change', () => {
              this.filters[property] = read(radio);
            });
          });
        });
      },
      filtered(groupKey) {
        return (this.groups[groupKey] || []).filter((row) =>
          Object.entries(this.filters).every(
            ([property, state]) =>
              state === 'any' || Boolean(row[property]) === (state === 'yes'),
          ),
        );
      },
      load() {
        this.loading = true;
        this.error = false;

        // The edit modal reports back right after saving; the list reflects the new policy then
        return window.axios
          .get(route('api.v1.payees.auto-record-candidates'))
          .then((response) => {
            this.groups = response.data;
          })
          .catch(() => {
            this.error = true;
          })
          .finally(() => {
            this.loading = false;
          });
      },
      formatMoney(value, currency) {
        return toFormattedCurrency(value, this.locale, currency);
      },
      formatNumber(value) {
        return toFormattedNumber(value, this.locale, {
          maximumFractionDigits: 2,
        });
      },
      formatPercent(value) {
        return (
          toFormattedNumber(Number(value) * 100, this.locale, {
            maximumFractionDigits: 0,
          }) + '%'
        );
      },
      __,
    },
  };
</script>
