<template>
  <div id="payeeProfileCard" class="card mb-3">
    <div class="card-header">
      <div class="card-title">{{ __('Recording profile') }}</div>
    </div>
    <div class="card-body">
      <p v-if="!profile" class="text-muted mb-0">
        {{
          __(
            'The profile is calculated overnight, or when you record a transaction for this payee.',
          )
        }}
      </p>
      <dl v-else class="row mb-0">
        <dt class="col-6">{{ __('Transactions analyzed') }}</dt>
        <dd class="col-6">{{ profile.sample_size }}</dd>

        <template v-if="profile.sample_size > 0">
          <dt class="col-6">{{ __('Usual category') }}</dt>
          <dd class="col-6">
            <template v-if="profile.dominant_category">
              {{ profile.dominant_category }}
              <span class="text-muted">
                ({{ formatPercent(profile.dominant_share) }})
              </span>
            </template>
            <span v-else class="text-muted">{{ __('None') }}</span>
          </dd>

          <dt class="col-6">{{ __('Consistency') }}</dt>
          <dd
            class="col-6"
            :title="
              __(
                'Lower confidence bound of the share of single-item transactions in the usual category.',
              )
            "
          >
            {{ formatNumber(profile.wilson_lower) }}
          </dd>

          <dt class="col-6">{{ __('Usual amount') }}</dt>
          <dd class="col-6">
            {{ formatMoney(profile.amount_median) }}
            <span class="text-muted">
              ({{ formatMoney(profile.amount_min) }} –
              {{ formatMoney(profile.amount_max) }})
            </span>
          </dd>
        </template>

        <dt class="col-6">{{ __('Auto-recording') }}</dt>
        <dd class="col-6">
          <span v-if="profile.qualifies" class="text-success">
            <i class="fa fa-check"></i> {{ __('Qualifies') }}
          </span>
          <span v-else class="text-muted">
            <template v-if="profile.missing">
              {{
                __('Missing :count transactions', { count: profile.missing })
              }}
            </template>
            <template v-else>{{ __('Does not qualify') }}</template>
          </span>
          <small class="d-block text-muted">{{ profile.reason }}</small>
        </dd>
      </dl>
    </div>
  </div>
</template>

<script>
  import {
    __,
    toFormattedCurrency,
    toFormattedNumber,
  } from '@/shared/lib/i18n';

  export default {
    name: 'PayeeProfileCard',
    props: {
      profile: { type: Object, default: null },
    },
    data() {
      return { locale: window.YAFFA.userSettings.locale };
    },
    methods: {
      formatMoney(value) {
        return toFormattedCurrency(value, this.locale, this.profile?.currency);
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
