<template>
  <div class="card">
    <div class="card-header">
      <div class="card-title">{{ title }}</div>
    </div>
    <div class="card-body no-datatable-search">
      <slot />
    </div>
  </div>
</template>

<script>
  // Reusable "card containing a DataTable" wrapper (see T-15 in
  // .ai/docs/specifications/frontend-review/tasks.md). Only owns the card/header/body
  // chrome - the table itself, its DataTables config, and any interaction wiring stay with
  // the caller (e.g. EditableDatedValueTable.vue), so this stays reusable by any future
  // "card + table" screen (AiDocumentTable, ImportDraftTable, ...) without dragging along
  // DataTables-specific behavior.
  export default {
    name: 'DataTableCard',
    props: {
      title: {
        type: String,
        required: true,
      },
    },
  };
</script>
