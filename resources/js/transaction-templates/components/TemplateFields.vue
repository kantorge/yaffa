<template>
  <div class="card mb-3" data-testid="template-fields">
    <div class="card-header">
      <div class="card-title">{{ __('Template') }}</div>
    </div>
    <div class="card-body">
      <div
        v-for="notice in visibleNotices"
        :key="notice.field"
        class="alert alert-warning alert-dismissible"
        role="alert"
      >
        {{ noticeText(notice) }}
        <button
          type="button"
          class="btn-close"
          :aria-label="__('Close')"
          @click="dismissed.push(notice.field)"
        ></button>
      </div>
      <div class="row">
        <div
          class="col-12 col-md-8 mb-3 mb-md-0"
          :class="{ 'has-error': errors.has('name') }"
        >
          <label class="form-label" for="template-name">
            {{ __('Template name') }}
          </label>
          <input
            id="template-name"
            :value="name"
            class="form-control"
            maxlength="191"
            type="text"
            @input="$emit('update:name', $event.target.value)"
          />
          <div v-if="errors.has('name')" class="form-text text-danger">
            {{ errors.get('name') }}
          </div>
        </div>
        <div class="col-12 col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input
              id="template-featured"
              :checked="isFeatured"
              class="form-check-input"
              type="checkbox"
              @change="$emit('update:isFeatured', $event.target.checked)"
            />
            <label class="form-check-label" for="template-featured">
              {{ __('Show on dashboard') }}
            </label>
          </div>
        </div>
      </div>
      <p class="form-text mb-0 mt-2">
        {{
          __(
            'A template does not store a date. Fill in only the fields you want to be prefilled.',
          )
        }}
      </p>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  const FIELD_LABELS = {
    account_from_id: 'From',
    account_to_id: 'To',
    account_id: 'Account',
    investment_id: 'Investment',
    category_id: 'Category',
    recommended_category_id: 'Category',
  };

  export default {
    name: 'TemplateFields',

    props: {
      name: { type: String, default: '' },
      isFeatured: { type: Boolean, default: false },
      // Stale references removed from the saved template: [{field, reason}]
      notices: { type: Array, default: () => [] },
      // The vform error bag of the form
      errors: { type: Object, required: true },
    },

    emits: ['update:name', 'update:isFeatured'],

    data() {
      return { dismissed: [] };
    },

    computed: {
      visibleNotices() {
        return this.notices.filter((n) => !this.dismissed.includes(n.field));
      },
    },

    methods: {
      __,

      noticeText(notice) {
        const last = notice.field.split('.').pop();
        return __(
          'The saved value of ":field" is no longer available (:reason) and was cleared.',
          {
            field: __(FIELD_LABELS[last] || 'Tag'),
            reason:
              notice.reason === 'inactive' ? __('inactive') : __('not found'),
          },
        );
      },
    },
  };
</script>
