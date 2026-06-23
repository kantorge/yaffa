<template>
  <div class="table-responsive border rounded">
    <table class="table table-sm table-bordered mb-0 small">
      <thead class="table-light">
        <tr>
          <th
            v-for="(header, i) in headers"
            :key="i"
            class="text-nowrap fw-normal text-muted"
            style="max-width: 160px; overflow: hidden; text-overflow: ellipsis"
            :title="header"
          >
            {{ header || `#${i + 1}` }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="dataRows.length === 0">
          <td :colspan="headers.length || 1" class="text-muted text-center py-2">
            {{ __('No data rows to preview') }}
          </td>
        </tr>
        <tr v-for="(row, ri) in dataRows" :key="ri">
          <td
            v-for="(cell, ci) in normalizedRow(row, headers.length)"
            :key="ci"
            class="text-nowrap"
            style="max-width: 200px; overflow: hidden; text-overflow: ellipsis"
            :title="cell"
          >
            {{ cell !== '' ? cell : '—' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'CsvPreviewTable',
    props: {
      headers: {
        type: Array,
        default: () => [],
      },
      dataRows: {
        type: Array,
        default: () => [],
      },
    },
    methods: {
      __,
      normalizedRow(row, length) {
        const arr = Array.isArray(row) ? [...row] : [];
        while (arr.length < length) arr.push('');
        return arr.slice(0, length);
      },
    },
  };
</script>
