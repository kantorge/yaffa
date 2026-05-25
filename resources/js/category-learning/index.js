import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import Swal from 'sweetalert2';

import { createApp } from 'vue';
import CategoryLearningForm from './components/CategoryLearningForm.vue';
import OnboardingCard from '@/dashboard/components/widgets/OnboardingCard.vue';

import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';
import { escapeHtml } from '@/shared/lib/helpers';
import { initializeSelect2 } from '@/shared/lib/select2';
import { booleanToTableIcon } from '@/shared/lib/datatable';
import * as toastHelpers from '@/shared/lib/toast';

initializeSelect2(window.YAFFA.userSettings.language);

const dataTableSelector = '#table';

const toNumericId = (value) => {
  const parsedValue = Number(value);
  return Number.isNaN(parsedValue) ? null : parsedValue;
};

const normalizeLearning = (learning) => {
  const active = learning.active ?? learning.status === 'active';
  const status = learning.status || (active ? 'active' : 'inactive');

  return {
    ...learning,
    active,
    status,
    category_id: toNumericId(learning.category?.id),
    category_name: learning.category?.full_name || learning.category?.name || __('Not set'),
    active_filter: active ? __('Yes') : __('No'),
  };
};

const vueApp = createApp({
  components: {
    CategoryLearningForm,
    OnboardingCard,
  },
  methods: {
    onLearningUpserted(learning) {
      const normalizedLearning = normalizeLearning(learning);
      const normalizedId = toNumericId(normalizedLearning.id);

      const existingIndex = window.categoryLearnings.findIndex(
        (item) => toNumericId(item.id) === normalizedId,
      );

      if (existingIndex !== -1) {
        window.categoryLearnings[existingIndex] = normalizedLearning;

        const row = window.table.row((_, data) => toNumericId(data.id) === normalizedId);
        if (row.any()) {
          row.data(normalizedLearning).draw(false);
        }

        toastHelpers.showSuccessToast(__('Category learning entry updated'));

        return;
      }

      window.categoryLearnings.push(normalizedLearning);
      window.table.row.add(normalizedLearning).draw(false);
      toastHelpers.showSuccessToast(__('Category learning entry added'));
    },
    showNewModal() {
      this.$refs.learningFormNew.show();
    },
    showEditModal(learning) {
      this.$refs.learningFormEdit.show(learning);
    },
  },
});

const app = vueApp.mount('#categoryLearningIndex');

window.onboardingTourSteps = [
  {
    element: '#table',
    popover: {
      title: __('Category learning'),
      description: __('Each row stores how a transaction description was learned for a category. This helps future AI suggestions stay consistent.'),
    },
  },
  {
    element: '#cardActions',
    popover: {
      title: __('Category learning actions'),
      description: __('Create a new category learning entry or merge two entries when they represent the same meaning.'),
    },
  },
  {
    element: '#cardFilters',
    popover: {
      title: __('Filters'),
      description: __('Use active, category, and search filters to quickly find the entries you want to review.'),
    },
  },
  {
    element: '#table_filter_active_yes',
    popover: {
      title: __('Active filter'),
      description: __('Inactive category learning entries stay available for review and can be reactivated later.'),
    },
  },
  {
    element: '#table_filter_search_text',
    popover: {
      title: __('Search category learning'),
      description: __('Search across description, category, and usage to locate specific category learning entries.'),
    },
  },
];

const mergeModalElement = document.getElementById('mergeCategoryLearningModal');
const mergeModal = new coreui.Modal(mergeModalElement);

const mergeSourceSelector = '#merge_source_learning';
const mergeTargetSelector = '#merge_target_learning';

const initializeMergeSelect = (selector, otherSelector) => {
  $(selector).select2({
    theme: 'bootstrap-5',
    placeholder: __('Select category learning entry'),
    allowClear: true,
    selectOnClose: false,
    dropdownParent: $(mergeModalElement),
    ajax: {
      url: '/api/v1/category-learning',
      dataType: 'json',
      delay: 150,
      data: function (params) {
        return {
          search: params.term,
          status: 'all',
        };
      },
      processResults: function (data) {
        const selectedOther = $(otherSelector).select2('data');
        const selectedOtherId = selectedOther.length > 0 ? Number(selectedOther[0].id) : null;
        const rows = Array.isArray(data) ? data : [];

        return {
          results: rows
            .filter((item) => Number(item.id) !== selectedOtherId)
            .map((item) => ({
              id: item.id,
              text: `${item.item_description} (${item.category?.full_name || item.category?.name || __('Not set')})`,
            })),
        };
      },
      cache: true,
    },
  });
};

initializeMergeSelect(mergeSourceSelector, mergeTargetSelector);
initializeMergeSelect(mergeTargetSelector, mergeSourceSelector);

const openMergeModal = (sourceLearning = null) => {
  $(mergeSourceSelector).empty().trigger('change');
  $(mergeTargetSelector).empty().trigger('change');

  if (sourceLearning) {
    const option = new Option(
      `${sourceLearning.item_description} (${sourceLearning.category_name})`,
      sourceLearning.id,
      true,
      true,
    );
    $(mergeSourceSelector).append(option).trigger('change');
  }

  mergeModal.show();
};

const buildTable = (rows) => {
  window.categoryLearnings = rows.map(normalizeLearning);

  window.table = $(dataTableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    data: window.categoryLearnings,
    columns: [
      {
        data: 'item_description',
        title: __('Description'),
        render: function (data, type) {
          if (type === 'display') {
            return `<span>${escapeHtml(data)}</span>`;
          }

          return data;
        },
      },
      {
        data: 'category_name',
        title: __('Category'),
      },
      {
        data: 'usage_count',
        title: __('Usage'),
        type: 'num',
      },
      {
        data: 'active',
        title: __('Active'),
        render: function (data, type) {
          return booleanToTableIcon(data, type);
        },
        className: 'text-center activeIcon',
      },
      {
        data: 'active_filter',
        title: __('Active filter'),
        visible: false,
        searchable: true,
      },
      {
        data: 'category_id',
        title: __('Category filter'),
        visible: false,
        searchable: true,
      },
      {
        data: 'id',
        title: __('Actions'),
        className: 'dt-nowrap',
        orderable: false,
        searchable: false,
        render: function (_data, _type, row) {
          return `<button class="btn btn-xs btn-primary button-edit-learning" data-id="${row.id}" title="${escapeHtml(__('Edit'))}"><i class="fa fa-edit"></i></button>
              <button class="btn btn-xs btn-info button-merge-learning-row" data-id="${row.id}" title="${escapeHtml(__('Merge category learning entries'))}"><i class="fa fa-random"></i></button>
                  <button class="btn btn-xs btn-danger button-delete-learning" data-id="${row.id}" title="${escapeHtml(__('Delete'))}"><i class="fa fa-trash"></i></button>`;
        },
      },
    ],
    createdRow: function (row, data) {
      if (!data.active) {
        $(row).addClass('text-muted');
      }
    },
    order: [[0, 'asc']],
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    stateSave: false,
    processing: true,
    paging: false,
    responsive: true,
    initComplete: function (settings) {
      const tableElement = $(settings.nTable);

      tableElement.on('click', '.button-edit-learning', function () {
        const id = Number($(this).data('id'));
        const row = window.table.row((_, data) => Number(data.id) === id);
        if (row.any()) {
          app.showEditModal(row.data());
        }
      });

      tableElement.on('click', '.button-merge-learning-row', function () {
        const id = Number($(this).data('id'));
        const row = window.table.row((_, data) => Number(data.id) === id);
        if (row.any()) {
          openMergeModal(row.data());
        }
      });

      tableElement.on('click', 'td.activeIcon > i', function () {
        const icon = $(this);
        const row = window.table.row(icon.parents('tr'));

        if (!row.any()) {
          return;
        }

        if (icon.hasClass('fa-spinner')) {
          return;
        }

        const learning = row.data();
        const routeName = learning.active
          ? 'api.v1.category-learning.deactivate'
          : 'api.v1.category-learning.activate';
        const errorMessage = learning.active
          ? __('Error while deactivating category learning entry')
          : __('Error while activating category learning entry');

        icon.removeClass().addClass('fa fa-spinner fa-spin');

        window.axios
          .post(route(routeName, { categoryLearning: learning.id }))
          .then((response) => app.onLearningUpserted(response.data))
          .catch(() => toastHelpers.showErrorToast(errorMessage))
          .finally(() => {
            row.invalidate().draw(false);
          });
      });

      tableElement.on('click', '.button-delete-learning', function () {
        const id = Number($(this).data('id'));

        Swal.fire({
          animation: false,
          text: __('Are you sure to want to delete this item?'),
          icon: 'warning',
          showCancelButton: true,
          cancelButtonText: __('Cancel'),
          confirmButtonText: __('Confirm'),
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary ms-3',
          },
        }).then((result) => {
          if (!result.isConfirmed) {
            return;
          }

          window.axios
            .delete(route('api.v1.category-learning.destroy', { categoryLearning: id }))
            .then(() => {
              window.categoryLearnings = window.categoryLearnings.filter((item) => Number(item.id) !== id);
              window.table
                .row((_, data) => Number(data.id) === id)
                .remove()
                .draw(false);
              toastHelpers.showSuccessToast(__('Category learning entry deleted'));
            })
            .catch(() => toastHelpers.showErrorToast(__('Error while deleting category learning entry')));
        });
      });
    },
  });

  $('input[name=table_filter_active]').on('change', function () {
    window.table.column(4).search(this.value).draw();
  });

  $('#table_filter_search_text').on('input', function () {
    window.table.search($(this).val()).draw();
  });

  $('#table_filter_search_text_clear').on('click', function () {
    $('#table_filter_search_text').val('');
    window.table.search('').draw();
  });

  const categoryFilterSelect = $('#table_filter_category');
  categoryFilterSelect.select2({
    theme: 'bootstrap-5',
    placeholder: __('Any'),
    allowClear: true,
    ajax: {
      url: '/api/v1/categories',
      dataType: 'json',
      delay: 150,
      data: function (params) {
        return {
          q: params.term || '*',
          withInactive: true,
        };
      },
      processResults: function (data) {
        const rows = Array.isArray(data) ? data : [];

        return {
          results: rows.map((item) => ({
            id: item.id,
            text: item.full_name,
          })),
        };
      },
      cache: true,
    },
  });

  categoryFilterSelect.on('change', function () {
    const selectedValue = $(this).val();

    if (!selectedValue) {
      window.table.column(5).search('').draw();
      return;
    }

    window.table.column(5).search(`^${selectedValue}$`, true, false).draw();
  });

  $('#button-new-learning').on('click', function () {
    app.showNewModal();
  });

  $('#button-merge-learning').on('click', function () {
    openMergeModal();
  });
};

window.axios
  .get(route('api.v1.category-learning.index'), {
    params: {
      status: 'all',
    },
  })
  .then((response) => {
    const rows = Array.isArray(response.data) ? response.data : [];
    buildTable(rows);
  })
  .catch(() => {
    toastHelpers.showErrorToast(__('Error while loading category learning entries'));
    buildTable([]);
  });

$('#button-submit-merge-learning').on('click', function () {
  const source = $(mergeSourceSelector).select2('data');
  const target = $(mergeTargetSelector).select2('data');

  if (source.length === 0 || target.length === 0) {
    toastHelpers.showErrorToast(__('Please select both source and target category learning entries'));
    return;
  }

  if (String(source[0].id) === String(target[0].id)) {
    toastHelpers.showErrorToast(__('Please select different category learning entries'));
    return;
  }

  window.axios
    .post(route('api.v1.category-learning.merge'), {
      source_id: source[0].id,
      target_id: target[0].id,
    })
    .then((response) => {
      const merged = normalizeLearning(response.data);
      const mergedId = Number(merged.id);
      const sourceId = Number(source[0].id);

      window.categoryLearnings = window.categoryLearnings
        .filter((item) => Number(item.id) !== sourceId && Number(item.id) !== mergedId);
      window.categoryLearnings.push(merged);

      window.table
        .rows((_, data) => Number(data.id) === sourceId || Number(data.id) === mergedId)
        .remove();
      window.table.row.add(merged).draw(false);

      mergeModal.hide();
      toastHelpers.showSuccessToast(__('Category learning entries merged'));
    })
    .catch((error) => {
      const message = error.response?.data?.message || __('Error while merging category learning entries');
      toastHelpers.showErrorToast(message);
    });
});