<template>
  <div class="card mb-3">
    <div class="card-header">
      <div
        class="card-title collapse-control"
        data-coreui-toggle="collapse"
        data-coreui-target="#cardFilters"
      >
        <i class="fa fa-angle-down"></i>
        {{ __('Filters') }}
      </div>
    </div>
    <ul
      class="list-group list-group-flush collapse show"
      aria-expanded="true"
      id="cardFilters"
    >
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <label class="form-label col-4" for="table_filter_status">
          {{ __('Status') }}
        </label>
        <select
          id="table_filter_status"
          class="form-select"
          v-model="filters.status"
          @change="emitFilters"
        >
          <option value="">{{ __('Any') }}</option>
          <option v-for="(label, key) in statusOptions" :key="key" :value="key">
            {{ label }}
          </option>
        </select>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <label class="form-label col-4" for="table_filter_source">
          {{ __('Source') }}
        </label>
        <select
          id="table_filter_source"
          class="form-select"
          v-model="filters.source"
          @change="emitFilters"
        >
          <option value="">{{ __('Any') }}</option>
          <option v-for="(label, key) in sourceOptions" :key="key" :value="key">
            {{ label }}
          </option>
        </select>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <label class="col-4" for="table_filter_search_text">
          {{ __('Search') }}
        </label>
        <div class="input-group">
          <input
            autocomplete="off"
            class="form-control"
            id="table_filter_search_text"
            type="text"
            v-model="filters.search"
            @input="emitFilters"
          />
          <button
            class="btn btn-outline-secondary"
            type="button"
            id="table_filter_search_text_clear"
            :title="__('Clear search')"
            @click="clearSearch"
          >
            <i class="fa fa-times"></i>
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
  import { reactive } from 'vue';
  import { __ } from '@/i18n';

  const props = defineProps({
    statusOptions: {
      type: Object,
      default: () => ({}),
    },
    initialStatus: {
      type: String,
      default: '',
    },
    sourceOptions: {
      type: Object,
      default: () => ({}),
    },
    initialSource: {
      type: String,
      default: '',
    },
    initialSearch: {
      type: String,
      default: '',
    },
  });

  const emit = defineEmits(['update']);

  const filters = reactive({
    status: props.initialStatus || '',
    source: props.initialSource || '',
    search: props.initialSearch || '',
  });

  const emitFilters = () => {
    emit('update', { ...filters });
  };

  const clearSearch = () => {
    filters.search = '';
    emitFilters();
  };
</script>
