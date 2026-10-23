<template>
  <div class="card mb-3">
    <div class="card-header">
      <div
        class="card-title collapse-control"
        data-coreui-toggle="collapse"
        data-coreui-target="#cardActions"
      >
        <i class="fa fa-angle-down"></i>
        {{ __('Actions') }}
      </div>
    </div>
    <ul
      id="cardActions"
      class="list-group list-group-flush collapse show"
      aria-expanded="true"
    >
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        {{ __('Upload document') }}
        <button
          class="btn btn-sm btn-outline-success"
          :title="__('Upload a new document')"
          @click="$emit('open-upload-form')"
        >
          <i class="fa fa-fw fa-upload"></i>
        </button>
      </li>
      <li
        v-if="canConfirmAll"
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        {{ __('Confirm all shown') }}
        <button
          type="button"
          class="btn btn-sm btn-outline-success"
          :title="__('Confirm all shown')"
          dusk="confirm-all"
          @click="$emit('confirm-all')"
        >
          <i class="fa fa-fw fa-check-double"></i>
        </button>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        {{ __('Reprocess all open documents') }}
        <button
          type="button"
          class="btn btn-sm btn-outline-warning"
          :title="__('Reprocess all open documents')"
          dusk="reprocess-open"
          @click="$emit('reprocess-open')"
        >
          <i class="fa fa-fw fa-rotate"></i>
        </button>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        {{ __('AI provider settings') }}
        <a
          class="btn btn-sm btn-outline-primary"
          :href="aiSettingsUrl"
          :title="__('AI provider settings')"
        >
          <i class="fa fa-fw fa-sliders"></i>
        </a>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        {{ __('Category learning') }}
        <a
          class="btn btn-sm btn-outline-info"
          :href="categoryLearningUrl"
          :title="__('Category learning')"
        >
          <i class="fa fa-fw fa-graduation-cap"></i>
        </a>
      </li>
    </ul>
  </div>
</template>

<script setup>
  import { __ } from '@/shared/lib/i18n';

  defineProps({
    // Shown while the review filter lists transactions awaiting confirmation
    canConfirmAll: { type: Boolean, default: false },
  });

  defineEmits(['open-upload-form', 'confirm-all', 'reprocess-open']);

  const route = window.route;
  const aiSettingsUrl = route('user.ai-settings');
  const categoryLearningUrl = route('category-learning.index');
</script>
