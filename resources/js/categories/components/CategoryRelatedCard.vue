<template>
  <div
    v-if="groups.length > 0 || learningEntries.length > 0"
    id="categoryRelatedCard"
    class="card mb-3"
  >
    <div class="card-header">
      <div class="card-title">{{ __('Related items') }}</div>
    </div>
    <ul class="list-group list-group-flush">
      <li v-for="group in groups" :key="group.label" class="list-group-item">
        <div class="small text-muted">{{ group.label }}</div>
        <template v-for="(payee, index) in group.payees" :key="payee.id">
          <span v-if="index > 0">, </span>
          <a
            :href="route('account-entity.show', { account_entity: payee.id })"
            >{{ payee.name }}</a
          >
        </template>
      </li>
      <li v-if="learningEntries.length > 0" class="list-group-item">
        <div class="small text-muted">
          {{ __('AI learning entries') }}
          (<a :href="route('category-learning.index')">{{ __('Manage') }}</a
          >)
        </div>
        <div
          v-for="entry in learningEntries"
          :key="entry.id"
          class="d-flex justify-content-between"
          :class="{ 'text-muted': !entry.active }"
        >
          <span class="text-break">{{ entry.item_description }}</span>
          <span class="ms-2">×{{ entry.usage_count }}</span>
        </div>
      </li>
    </ul>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'CategoryRelatedCard',
    props: {
      category: { type: Object, required: true },
      learningEntries: { type: Array, required: true },
    },
    computed: {
      groups() {
        return [
          {
            label: __('Payees using it as default category'),
            payees: this.category.payees_defaulting,
          },
          {
            label: __('Payees preferring it'),
            payees: this.category.payees_preferring,
          },
          {
            label: __('Payees not preferring it'),
            payees: this.category.payees_not_preferring,
          },
        ].filter((group) => group.payees?.length > 0);
      },
    },
    methods: { __ },
  };
</script>
