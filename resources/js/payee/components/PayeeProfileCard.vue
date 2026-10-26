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

          <dt class="col-6">{{ __('Usual amount') }}</dt>
          <dd class="col-6">
            {{ formatMoney(profile.amount_min) }} –
            {{ formatMoney(profile.amount_max) }}
          </dd>
        </template>

        <dt class="col-6">
          {{ __('History check') }}
          <info-popover
            :content="
              __(
                'Checks whether the history of this payee is consistent enough for automatic recording, as set in your AI settings. Your override below is not part of this check.',
              )
            "
          ></info-popover>
        </dt>
        <dd class="col-6">
          <span :class="status.class">
            <i :class="status.icon"></i> {{ status.label }}
          </span>
          <info-popover
            v-if="profile.reason"
            :content="
              profile.missing && profile.history_status !== 'qualifying'
                ? __('Missing :count transactions', {
                    count: profile.missing,
                  }) +
                  '. ' +
                  profile.reason
                : profile.reason
            "
          ></info-popover>
        </dd>

        <dt class="col-6">
          {{ __('Auto-recording') }}
          <info-popover
            :content="
              __(
                'Always allow skips the history check, but the payee, account, amount and duplicate checks still apply. Never means documents of this payee always wait for your review.',
              )
            "
          ></info-popover>
        </dt>
        <dd class="col-6">{{ policyLabel }}</dd>

        <dt class="col-6">
          {{ __('Itemization') }}
          <info-popover
            :content="
              __(
                'Itemization expected is for payees where you record the individual items of a receipt, such as a grocery store.',
              )
            "
          ></info-popover>
        </dt>
        <dd class="col-6">
          {{
            profile.itemization_expected
              ? __('Itemization expected')
              : __('Summary is sufficient')
          }}
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
  import InfoPopover from '@/shared/ui/InfoPopover.vue';

  export default {
    name: 'PayeeProfileCard',
    components: { InfoPopover },
    props: {
      profile: { type: Object, default: null },
    },
    data() {
      return { locale: window.YAFFA.userSettings.locale };
    },
    computed: {
      status() {
        return {
          qualifying: {
            label: __('Qualifying'),
            icon: 'fa fa-check',
            class: 'text-success',
          },
          near: {
            label: __('Almost there'),
            icon: 'fa fa-hourglass-half',
            class: 'text-warning',
          },
          other: {
            label: __('Other'),
            icon: 'fa fa-minus',
            class: 'text-muted',
          },
        }[this.profile.history_status];
      },
      policyLabel() {
        return (
          {
            always: __('Always allow'),
            never: __('Never'),
          }[this.profile.auto_record_policy] ??
          __('Automatic (based on history)')
        );
      },
    },
    methods: {
      formatMoney(value) {
        return toFormattedCurrency(value, this.locale, this.profile?.currency);
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
