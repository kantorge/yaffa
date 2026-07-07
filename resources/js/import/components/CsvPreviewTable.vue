<template>
  <div class="table-responsive border rounded">
    <table class="table table-sm table-bordered mb-0 small">
      <thead>
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
      <tbody class="table-group-divider">
        <tr v-if="dataRows.length === 0">
          <td
            :colspan="headers.length || 1"
            class="text-muted text-center py-2"
          >
            {{ __('No data rows to preview') }}
          </td>
        </tr>
        <tr v-for="(row, row_index) in dataRows" :key="row_index">
          <td
            v-for="(cell, cell_index) in normalizedRow(row, headers.length)"
            :key="cell_index"
            class="text-nowrap"
            style="max-width: 200px; overflow: hidden; text-overflow: ellipsis"
            :title="cell"
          >
            {{ formatCellValue(cell) !== '' ? formatCellValue(cell) : '—' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
  import { __, toFormattedCurrency } from '@/shared/lib/i18n';

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
      accountCurrency: {
        type: Object,
        default: null,
      },
    },
    methods: {
      __,
      normalizedRow(row, length) {
        const arr = Array.isArray(row) ? [...row] : [];
        while (arr.length < length) arr.push('');
        return arr.slice(0, length);
      },
      formatCellValue(cell) {
        if (cell === '' || cell === null || cell === undefined) {
          return cell;
        }

        const stringValue = String(cell).trim();
        if (!stringValue) {
          return cell;
        }

        const numValue = Number(stringValue);
        if (Number.isNaN(numValue)) {
          return cell;
        }

        if (!this.accountCurrency) {
          return stringValue;
        }

        return toFormattedCurrency(
          numValue,
          window.YAFFA?.userSettings?.locale || undefined,
          this.accountCurrency,
        );
      },
    },
  };
</script>
