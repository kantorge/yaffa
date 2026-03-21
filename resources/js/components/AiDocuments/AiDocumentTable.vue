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
  const detectedDateRange = ref({ from: null, to: null });
  const route = window.route;
  const RESIZE_RECALC_DELAY_MS = 100;
  const COLUMN_INDEX = {
    status: 1,
    source: 2,
    receivedAt: 3,
    detectedDate: 4,
    detectedPayee: 5,
    detectedAmount: 6,
    detectedAccount: 7,
    linkedTransaction: 8,
  };

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

  const escapeHtml = (value) => {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  };

  const isUnidentified = (value) =>
    value === null || typeof value === 'undefined' || value === '';

  const normalizeDateForFilter = (value) => {
    if (!value) {
      return null;
    }

    if (value instanceof Date) {
      if (Number.isNaN(value.getTime())) {
        return null;
      }

      return value.toISOString().slice(0, 10);
    }

    const strValue = String(value).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(strValue)) {
      return strValue;
    }

    const parsedDate = new Date(strValue);
    if (Number.isNaN(parsedDate.getTime())) {
      return null;
    }

    return parsedDate.toISOString().slice(0, 10);
  };

  const toDisplayDate = (isoDate) => {
    if (!isoDate) {
      return __('Not available');
    }

    const parsedDate = new Date(`${isoDate}T00:00:00`);
    if (Number.isNaN(parsedDate.getTime())) {
      return isoDate;
    }

    return parsedDate.toLocaleDateString(window.YAFFA.userSettings.locale);
  };

  const getDraftData = (document) => document?.processed_transaction_data || {};
  const getRawData = (document) => getDraftData(document)?.raw || {};
  const getMatchedEntities = (document) =>
    getDraftData(document)?.matched_entities || {};

  const getDraftTransactionType = (document) => {
    const draftData = getDraftData(document);
    const rawData = getRawData(document);

    return rawData.transaction_type || draftData.transaction_type || null;
  };

  const isStandardDocument = (document) =>
    getDraftData(document)?.config_type === 'standard';
  const isTransferDocument = (document) =>
    getDraftTransactionType(document) === 'transfer';

  const getDetectedTransactionDate = (document) => {
    const draftData = getDraftData(document);
    const rawData = getRawData(document);

    return normalizeDateForFilter(draftData.date || rawData.date || null);
  };

  const renderMatchHint = ({ isMatched }) => {
    if (isMatched) {
      return `
        <span class="text-muted small ms-1" title="${escapeHtml(__('Matched database entity'))}">
          <i class="fa fa-check-circle text-success"></i>
        </span>`;
    }

    return `
      <span class="badge bg-secondary ms-1" title="${escapeHtml(__('Using extracted text'))}">
        ${escapeHtml(__('Text'))}
      </span>`;
  };

  const renderMatchedEntity = ({
    rawValue,
    matchedEntity,
    allowLink = true,
  }) => {
    if (matchedEntity?.matched) {
      const name = escapeHtml(
        matchedEntity?.name || rawValue || __('Not available'),
      );
      const hint = renderMatchHint({ isMatched: true });

      if (allowLink && matchedEntity.url) {
        const url = escapeHtml(matchedEntity.url);
        return `<a href="${url}" target="_blank" rel="noopener noreferrer">${name}</a>${hint}`;
      }

      return `<span>${name}</span>${hint}`;
    }

    if (isUnidentified(rawValue)) {
      return `<span class="text-muted text-italic">${escapeHtml(__('Not available'))}</span>`;
    }

    return `<span>${escapeHtml(rawValue)}</span>${renderMatchHint({ isMatched: false })}`;
  };

  const getDetectedPayeeForSearch = (document) => {
    if (!isStandardDocument(document) || isTransferDocument(document)) {
      return '';
    }

    const rawData = getRawData(document);
    const matchedEntities = getMatchedEntities(document);

    return matchedEntities.payee?.name || rawData.payee || '';
  };

  const renderDetectedPayee = (_value, type, row) => {
    if (type !== 'display') {
      return row.detected_payee_search || '';
    }

    if (!isStandardDocument(row) || isTransferDocument(row)) {
      return `<span class="text-muted text-italic">${escapeHtml(__('Not available'))}</span>`;
    }

    const rawData = getRawData(row);
    const matchedEntities = getMatchedEntities(row);
    const matchedPayee = matchedEntities?.payee;

    // If matched entity exists with matched flag
    if (matchedPayee?.matched) {
      const name = escapeHtml(matchedPayee?.name || rawData?.payee || __('Not available'));
      const hint = renderMatchHint({ isMatched: true });
      return `<span>${name}</span>${hint}`;
    }

    // No match - fall back to raw value or "Not available"
    if (isUnidentified(rawData?.payee)) {
      return `<span class="text-muted text-italic">${escapeHtml(__('Not available'))}</span>`;
    }

    return `<span>${escapeHtml(rawData.payee)}</span>${renderMatchHint({ isMatched: false })}`;
  };

  const getDetectedAmountForSearch = (document) => {
    if (!isStandardDocument(document)) {
      return '';
    }

    const rawData = getRawData(document);

    return isUnidentified(rawData.amount) ? '' : String(rawData.amount);
  };

  const renderDetectedAmount = (_value, type, row) => {
    if (type !== 'display') {
      return row.detected_amount_search || '';
    }

    if (!isStandardDocument(row)) {
      return `<span class="text-muted text-italic">${escapeHtml(__('Not available'))}</span>`;
    }

    if (isUnidentified(row.detected_amount_search)) {
      return `<span class="text-muted text-italic">${escapeHtml(__('Not available'))}</span>`;
    }

    return row.detected_amount_search;
  };

  const getDetectedAccountForSearch = (document) => {
    const rawData = getRawData(document);
    const matchedEntities = getMatchedEntities(document);

    if (isTransferDocument(document)) {
      const fromValue =
        matchedEntities.account_from?.name || rawData.account_from || '';
      const toValue =
        matchedEntities.account_to?.name || rawData.account_to || '';

      return [fromValue, toValue].filter(Boolean).join(' -> ');
    }

    return matchedEntities.account?.name || rawData.account || '';
  };

  const renderDetectedAccount = (_value, type, row) => {
    if (type !== 'display') {
      return row.detected_account_search || '';
    }

    const rawData = getRawData(row);
    const matchedEntities = getMatchedEntities(row);

    if (isTransferDocument(row)) {
      const fromHtml = renderMatchedEntity({
        rawValue: rawData.account_from,
        matchedEntity: matchedEntities.account_from,
      });
      const toHtml = renderMatchedEntity({
        rawValue: rawData.account_to,
        matchedEntity: matchedEntities.account_to,
      });

      return `
        <div class="detected-account-pair">
          <div><span class="text-muted me-1">${escapeHtml(__('From'))}:</span>${fromHtml}</div>
          <div><span class="text-muted me-1">${escapeHtml(__('To'))}:</span>${toHtml}</div>
        </div>`;
    }

    return renderMatchedEntity({
      rawValue: rawData.account,
      matchedEntity: matchedEntities.account,
    });
  };

  const prepareDocument = (document) => {
    const detectedDate = getDetectedTransactionDate(document);

    return {
      ...document,
      created_at: document.created_at ? new Date(document.created_at) : null,
      display_title: getTitle(document),
      detected_transaction_date: detectedDate,
      detected_payee_search: getDetectedPayeeForSearch(document),
      detected_amount_search: getDetectedAmountForSearch(document),
      detected_account_search: getDetectedAccountForSearch(document),
    };
  };

  const prepareDocuments = (documents) =>
    documents.map((document) => prepareDocument(document));

  const updateRow = (row, updates) => {
    const data = row.data();
    row.data({ ...data, ...updates }).invalidate();
  };

  const reprocessDocument = (documentId, row) => {
    if (ajaxIsBusy.value) {
      return;
    }

    Swal.fire({
      text: __(
        'Reprocessing will remove the previous extraction data and AI chat history. Do you want to continue?',
      ),
      icon: 'warning',
      showCancelButton: true,
      cancelButtonText: __('Cancel'),
      confirmButtonText: __('Reprocess'),
      buttonsStyling: false,
      customClass: {
        confirmButton: 'btn btn-warning',
        cancelButton: 'btn btn-outline-secondary ms-3',
      },
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      ajaxIsBusy.value = true;

      window.axios
        .post(
          window.route('api.v1.documents.reprocess', {
            aiDocument: documentId,
          }),
        )
        .then((response) => {
          updateRow(
            row,
            prepareDocument({
              ...row.data(),
              status: response.data.status,
              processed_transaction_data: null,
            }),
          );
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
        .delete(route('api.v1.documents.destroy', { aiDocument: documentId }))
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

  const detectedDateRangeFilterFn = (settings, _searchData, dataIndex) => {
    if (!tableElement.value || settings.nTable !== tableElement.value) {
      return true;
    }

    const from = detectedDateRange.value.from;
    const to = detectedDateRange.value.to;

    if (!from && !to) {
      return true;
    }

    const rowData = settings.aoData?.[dataIndex]?._aData;
    const rowDate = rowData?.detected_transaction_date || null;

    if (!rowDate) {
      return false;
    }

    if (from && rowDate < from) {
      return false;
    }

    if (to && rowDate > to) {
      return false;
    }

    return true;
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
                  <a href="${route('ai-documents.show', {
                    aiDocument: row.id,
                  })}" title="${escapeHtml(value)}" class="ai-document-title-link">${escapeHtml(value)}</a>
                </span>
              </div>`;
          },
          type: 'html',
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
        },
        {
          data: 'detected_transaction_date',
          title: __('Detected date'),
          render: (value, type) => {
            if (type === 'display') {
              return toDisplayDate(value);
            }

            return value || '';
          },
          className: 'dt-nowrap',
          type: 'date',
        },
        {
          data: 'detected_payee_search',
          title: __('Detected payee'),
          render: renderDetectedPayee,
          type: 'html',
        },
        {
          data: 'detected_amount_search',
          title: __('Detected amount'),
          render: renderDetectedAmount,
          className: 'dt-nowrap',
        },
        {
          data: 'detected_account_search',
          title: __('Detected account'),
          render: renderDetectedAccount,
          type: 'html',
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
        },
      ],
      order: [[COLUMN_INDEX.receivedAt, 'desc']],
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
          window
            .$('td:eq(' + COLUMN_INDEX.linkedTransaction + ')', row)
            .addClass('text-muted text-italic');
        }
      },
    });

    window.$.fn.dataTable.ext.search.push(detectedDateRangeFilterFn);

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
            window.location.href = route('ai-documents.show', {
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

            window.location.href = route('transaction.open', {
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

  const applyFilters = ({
    status,
    source,
    search,
    detectedDateFrom,
    detectedDateTo,
  }) => {
    if (!table.value) {
      return;
    }

    const statusValue = status ? props.statusLabels[status] || status : '';
    const sourceValue = source ? props.sourceLabels[source] || source : '';

    // Use exact match with regex for status and source filters
    table.value.column(COLUMN_INDEX.status).search(
      statusValue ? `^${escapeRegex(statusValue)}$` : '',
      true, // regex
      false, // smart
      true, // case insensitive
    );

    table.value.column(COLUMN_INDEX.source).search(
      sourceValue ? `^${escapeRegex(sourceValue)}$` : '',
      true, // regex
      false, // smart
      true, // case insensitive
    );

    detectedDateRange.value = {
      from: normalizeDateForFilter(detectedDateFrom),
      to: normalizeDateForFilter(detectedDateTo),
    };

    table.value.search(search || '');
    table.value.draw();

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

    const dateFilterIndex = window.$.fn.dataTable.ext.search.indexOf(
      detectedDateRangeFilterFn,
    );

    if (dateFilterIndex > -1) {
      window.$.fn.dataTable.ext.search.splice(dateFilterIndex, 1);
    }
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

  .detected-account-pair {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
</style>
