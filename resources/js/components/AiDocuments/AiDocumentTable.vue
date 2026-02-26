<template>
  <div class="card mb-3">
    <div class="card-body no-datatable-search">
      <table
        class="table table-striped table-bordered table-hover"
        id="ai-document-table"
        role="grid"
        aria-label="List of AI documents"
        ref="tableElement"
      ></table>
    </div>
  </div>
</template>

<script setup>
  import 'datatables.net-bs5';
  import 'datatables.net-select-bs5';
  import 'datatables-contextual-actions';
  import Swal from 'sweetalert2';
  import { onMounted, onUnmounted, ref, watch } from 'vue';
  import { __, getDataTablesLanguageOptions } from '@/i18n';
  import * as dataTableHelpers from '../dataTableHelper';
  import * as toastHelpers from '@/toast';

  const props = defineProps({
    documents: {
      type: Array,
      default: () => [],
    },
    statusLabels: {
      type: Object,
      default: () => ({}),
    },
    sourceLabels: {
      type: Object,
      default: () => ({}),
    },
  });

  const tableElement = ref(null);
  const table = ref(null);
  const ajaxIsBusy = ref(false);
  const RESIZE_RECALC_DELAY_MS = 100;

  const statusBadgeClass = (status) => {
    switch (status) {
      case 'ready_for_processing':
        return 'bg-info';
      case 'processing':
        return 'bg-primary';
      case 'processing_failed':
        return 'bg-danger';
      case 'ready_for_review':
        return 'bg-warning';
      case 'finalized':
        return 'bg-success';
      default:
        return 'bg-secondary';
    }
  };

  const getTitle = (document) => {
    if (document.received_mail?.subject) {
      return document.received_mail.subject;
    }

    if (document.files && document.files.length > 0) {
      return document.files[0].file_name;
    }

    return __('Document #:id', { id: document.id });
  };

  const prepareDocuments = (documents) =>
    documents.map((document) => ({
      ...document,
      created_at: document.created_at ? new Date(document.created_at) : null,
      display_title: getTitle(document),
    }));

  const updateRow = (row, updates) => {
    const data = row.data();
    row.data({ ...data, ...updates }).invalidate();
  };

  const reprocessDocument = (documentId, row) => {
    if (ajaxIsBusy.value) {
      return;
    }

    ajaxIsBusy.value = true;

    window.axios
      .post(window.route('api.documents.reprocess', { aiDocument: documentId }))
      .then((response) => {
        updateRow(row, { status: response.data.status });
        toastHelpers.showSuccessToast(response.data.message);
      })
      .catch((error) => {
        toastHelpers.showErrorToast(
          __('Error while reprocessing document: :errorMessage', {
            errorMessage: error.response?.data?.message || error.message,
          }),
        );
      })
      .finally(() => {
        ajaxIsBusy.value = false;
      });
  };

  const deleteDocument = (documentId, row) => {
    if (ajaxIsBusy.value) {
      return;
    }

    ajaxIsBusy.value = true;

    Swal.fire({
      text: __('Are you sure you want to delete this document?'),
      icon: 'warning',
      showCancelButton: true,
      cancelButtonText: __('Cancel'),
      confirmButtonText: __('Delete'),
      buttonsStyling: false,
      customClass: {
        confirmButton: 'btn btn-danger',
        cancelButton: 'btn btn-outline-secondary ms-3',
      },
    }).then((result) => {
      if (!result.isConfirmed) {
        ajaxIsBusy.value = false;
        return;
      }

      window.axios
        .delete(
          window.route('api.documents.destroy', { aiDocument: documentId }),
        )
        .then(() => {
          row.remove().draw();
          toastHelpers.showSuccessToast(__('Document deleted'));
        })
        .catch((error) => {
          toastHelpers.showErrorToast(
            __('Error while deleting document: :errorMessage', {
              errorMessage: error.response?.data?.message || error.message,
            }),
          );
        })
        .finally(() => {
          ajaxIsBusy.value = false;
        });
    });
  };

  const canReprocess = (status) =>
    ['ready_for_review', 'processing_failed', 'finalized'].includes(status);

  const recalculateTableLayout = () => {
    if (!table.value) {
      return;
    }

    table.value.columns.adjust();

    if (table.value.responsive && table.value.responsive.recalc) {
      table.value.responsive.recalc();
    }

    table.value.draw(false);
  };

  const refreshRows = (documents) => {
    if (!table.value) {
      return;
    }

    const data = prepareDocuments(documents || []);
    table.value.clear();
    table.value.rows.add(data);
    table.value.draw(false);

    window.requestAnimationFrame(() => {
      recalculateTableLayout();
      window.setTimeout(() => {
        recalculateTableLayout();
      }, 120);
    });
  };

  onMounted(() => {
    const data = prepareDocuments(props.documents);

    table.value = window.$(tableElement.value).DataTable({
      language: getDataTablesLanguageOptions() || undefined,
      data,
      columns: [
        {
          data: 'display_title',
          title: __('Title'),
          render: (value, type, row) => {
            if (type !== 'display') {
              return value;
            }

            return `
              <div class="d-flex justify-content-start align-items-center">
                <i class="hover-icon me-2 fa-fw fa-solid fa-ellipsis-vertical"></i>
                <span class="ai-document-title-wrapper">
                  <a href="${window.route('ai-documents.show', {
                    aiDocument: row.id,
                  })}" title="${value}" class="ai-document-title-link">${value}</a>
                </span>
              </div>`;
          },
          type: 'html',
          width: '52%',
        },
        {
          data: 'status',
          title: __('Status'),
          render: (value, type) => {
            if (type === 'filter') {
              return props.statusLabels[value] || value;
            }

            const label = props.statusLabels[value] || value;
            return `<span class="badge ${statusBadgeClass(value)}">${label}</span>`;
          },
          width: '12%',
        },
        {
          data: 'source_type',
          title: __('Source'),
          render: (value, type) => {
            if (type === 'filter') {
              return props.sourceLabels[value] || value;
            }

            return props.sourceLabels[value] || value;
          },
          width: '10%',
        },
        {
          data: 'created_at',
          title: __('Received at'),
          render: (value, type) => {
            if (type === 'display' && value && value.toLocaleString) {
              return value.toLocaleString(window.YAFFA.userSettings.locale);
            }

            return value;
          },
          className: 'dt-nowrap',
          type: 'date',
          width: '14%',
        },
        {
          data: 'transaction',
          title: __('Linked transaction'),
          render: (value, _type) => {
            if (!value) {
              return __('Not available');
            }

            return (
              dataTableHelpers.dataTablesActionButton(value.id, 'quickView') +
              dataTableHelpers.dataTablesActionButton(value.id, 'show')
            );
          },
          className: 'dt-nowrap',
          orderable: false,
          searchable: false,
          width: '12%',
        },
      ],
      order: [[3, 'desc']],
      autoWidth: false,
      deferRender: true,
      scrollY: '500px',
      scrollCollapse: true,
      stateSave: false,
      processing: true,
      paging: false,
      responsive: true,
      select: {
        select: true,
        info: false,
        style: 'os',
      },
      createdRow: (row, data) => {
        if (!data.transaction) {
          window.$('td:eq(4)', row).addClass('text-muted text-italic');
        }
      },
    });

    table.value.contextualActions({
      contextMenuClasses: ['text-primary'],
      deselectAfterAction: true,
      contextMenu: {
        enabled: true,
        isMulti: false,
        headerRenderer: (selectedRows) => selectedRows[0].display_title,
        triggerButtonSelector: '.hover-icon',
      },
      buttonList: {
        enabled: false,
      },
      items: [
        {
          type: 'option',
          title: __('Show details'),
          iconClass: 'fa fa-fw fa-search',
          contextMenuClasses: ['text-success fw-bold'],
          action: (selectedRows) => {
            window.location.href = window.route('ai-documents.show', {
              aiDocument: selectedRows[0].id,
            });
          },
        },
        {
          type: 'option',
          title: __('Open linked transaction'),
          iconClass: 'fa fa-fw fa-external-link',
          contextMenuClasses: ['text-info fw-bold'],
          isHidden: (row) => !row.transaction,
          action: (selectedRows) => {
            if (!selectedRows[0].transaction) {
              return;
            }

            window.location.href = window.route('transaction.open', {
              transaction: selectedRows[0].transaction.id,
              action: 'show',
            });
          },
        },
        {
          type: 'divider',
        },
        {
          type: 'option',
          title: __('Reprocess document'),
          iconClass: 'fa fa-fw fa-repeat',
          contextMenuClasses: ['text-warning fw-bold'],
          isHidden: (row) => !canReprocess(row.status),
          action: (selectedRows) => {
            const row = table.value.row(
              (idx, data) => data.id === selectedRows[0].id,
            );
            reprocessDocument(selectedRows[0].id, row);
          },
        },
        {
          type: 'divider',
        },
        {
          type: 'option',
          title: __('Delete document'),
          iconClass: 'fa fa-trash',
          contextMenuClasses: ['text-danger fw-bold'],
          isDisabled: () => ajaxIsBusy.value,
          action: (selectedRows) => {
            const row = table.value.row(
              (idx, data) => data.id === selectedRows[0].id,
            );
            deleteDocument(selectedRows[0].id, row);
          },
        },
      ],
    });

    dataTableHelpers.initializeQuickViewButton('#ai-document-table');

    window.requestAnimationFrame(() => {
      recalculateTableLayout();
    });

    window.addEventListener('resize', handleWindowResize);
  });

  const handleWindowResize = () => {
    window.setTimeout(() => {
      recalculateTableLayout();
    }, RESIZE_RECALC_DELAY_MS);
  };

  const escapeRegex = (str) => {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  };

  const applyFilters = ({ status, source, search }) => {
    if (!table.value) {
      return;
    }

    const statusValue = status ? props.statusLabels[status] || status : '';
    const sourceValue = source ? props.sourceLabels[source] || source : '';

    // Use exact match with regex for status and source filters
    table.value
      .column(1)
      .search(
        statusValue ? `^${escapeRegex(statusValue)}$` : '',
        true, // regex
        false, // smart
        true, // case insensitive
      )
      .draw();

    table.value
      .column(2)
      .search(
        sourceValue ? `^${escapeRegex(sourceValue)}$` : '',
        true, // regex
        false, // smart
        true, // case insensitive
      )
      .draw();

    table.value.search(search || '').draw();

    recalculateTableLayout();
  };

  watch(
    () => props.documents,
    (newDocuments) => {
      refreshRows(newDocuments);
    },
    { deep: true },
  );

  onUnmounted(() => {
    window.removeEventListener('resize', handleWindowResize);
  });

  defineExpose({ applyFilters });
</script>

<style scoped>
  .ai-document-title-wrapper {
    min-width: 0;
    flex: 1 1 auto;
  }

  .ai-document-title-link {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: bottom;
  }
</style>
