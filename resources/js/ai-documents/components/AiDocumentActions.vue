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
      class="list-group list-group-flush collapse show"
      aria-expanded="true"
      id="cardActions"
    >
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        {{ __('Upload document') }}
        <button
          class="btn btn-sm btn-outline-success"
          @click="$emit('open-upload-form')"
          :title="__('Upload a new document')"
        >
          <i class="fa fa-fw fa-upload"></i>
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
    </ul>
  </div>
</template>

<script setup>
  import { __ } from '@/shared/lib/i18n';

  defineEmits(['open-upload-form']);

  const route = window.route;
  const aiSettingsUrl = route('user.ai-settings');
</script>
