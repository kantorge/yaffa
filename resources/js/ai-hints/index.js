import 'datatables.net-bs5';

import { createApp } from 'vue';
import AiHintForm from './components/AiHintForm.vue';
import OnboardingCard from '@/dashboard/components/widgets/OnboardingCard.vue';

import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';
import { escapeHtml } from '@/shared/lib/helpers';
import { booleanToTableIcon } from '@/shared/lib/datatable';
import * as toastHelpers from '@/shared/lib/toast';
import { confirmDelete } from '@/shared/lib/confirm';

const dataTableSelector = '#table';

const normalizeHint = (hint) => ({
  ...hint,
  active_filter: hint.active ? __('Yes') : __('No'),
});

const vueApp = createApp({
  components: {
    AiHintForm,
    OnboardingCard,
  },
  methods: {
    onHintSaved(hint) {
      const normalizedHint = normalizeHint(hint);
      const row = window.table.row((_, data) => data.id === normalizedHint.id);

      if (row.any()) {
        row.data(normalizedHint).draw(false);
        toastHelpers.showSuccessToast(__('AI hint updated'));

        return;
      }

      window.table.row.add(normalizedHint).draw(false);
      toastHelpers.showSuccessToast(__('AI hint added'));
    },
    showNewModal() {
      this.$refs.hintFormNew.show();
    },
    showEditModal(hint) {
      this.$refs.hintFormEdit.show(hint);
    },
  },
});

const app = vueApp.mount('#aiHintsIndex');

window.onboardingTourSteps = [
  {
    element: '#table',
    popover: {
      title: __('AI hints'),
      description: __(
        'Each row is a guideline that is sent to the AI when it extracts a document, so recurring documents are interpreted the same way every time.',
      ),
    },
  },
  {
    element: '#cardActions',
    popover: {
      title: __('AI hint actions'),
      description: __(
        'Create a new AI hint. Use the trigger text to apply it only to documents containing that text, such as the sender or subject of a bank notification.',
      ),
    },
  },
  {
    element: '#cardFilters',
    popover: {
      title: __('Filters'),
      description: __(
        'Use the active filter and the search to quickly find the hints you want to review.',
      ),
    },
  },
  {
    element: '#table_filter_active_yes',
    popover: {
      title: __('Active filter'),
      description: __(
        'Inactive AI hints are not sent to the AI, but stay available and can be reactivated by clicking their active icon.',
      ),
    },
  },
];

const buildTable = (rows) => {
  window.table = $(dataTableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    data: rows.map(normalizeHint),
    columns: [
      {
        data: 'name',
        title: __('Name'),
        render: (data) => escapeHtml(data),
      },
      {
        data: 'trigger_text',
        title: __('Trigger text'),
        render: (data) => escapeHtml(data ?? ''),
      },
      {
        data: 'hint',
        title: __('Hint'),
        render: (data, type) =>
          type === 'display'
            ? `<span style="white-space: pre-wrap">${escapeHtml(data)}</span>`
            : data,
      },
      {
        data: 'active',
        title: __('Active'),
        render: (data, type) => booleanToTableIcon(data, type),
        className: 'text-center activeIcon',
      },
      {
        data: 'active_filter',
        title: __('Active filter'),
        visible: false,
        searchable: true,
      },
      {
        data: 'id',
        title: __('Actions'),
        className: 'dt-nowrap',
        orderable: false,
        searchable: false,
        render: (id) =>
          `<button class="btn btn-xs btn-primary button-edit-hint" data-id="${id}" title="${escapeHtml(__('Edit'))}"><i class="fa fa-edit"></i></button>
           <button class="btn btn-xs btn-danger button-delete-hint" data-id="${id}" title="${escapeHtml(__('Delete'))}"><i class="fa fa-trash"></i></button>`,
      },
    ],
    createdRow: (row, data) => {
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
    initComplete: function (settings) {
      const tableElement = $(settings.table);

      tableElement.on('click', '.button-edit-hint', function () {
        const id = Number($(this).data('id'));
        const row = window.table.row((_, data) => data.id === id);
        if (row.any()) {
          app.showEditModal(row.data());
        }
      });

      tableElement.on('click', 'td.activeIcon > i', function () {
        const icon = $(this);
        const row = window.table.row(icon.parents('tr'));

        if (!row.any() || icon.hasClass('fa-spinner')) {
          return;
        }

        const hint = row.data();

        icon.removeClass().addClass('fa fa-spinner fa-spin');

        window.axios
          .patch(route('api.v1.ai-hints.update', { aiHint: hint.id }), {
            name: hint.name,
            trigger_text: hint.trigger_text,
            hint: hint.hint,
            active: !hint.active,
          })
          .then((response) => app.onHintSaved(response.data))
          .catch(() =>
            toastHelpers.showErrorToast(__('Error while saving AI hint')),
          )
          .finally(() => {
            row.invalidate().draw(false);
          });
      });

      tableElement.on('click', '.button-delete-hint', function () {
        const id = Number($(this).data('id'));

        confirmDelete(__('Are you sure to want to delete this item?')).then(
          (result) => {
            if (!result.isConfirmed) {
              return;
            }

            window.axios
              .delete(route('api.v1.ai-hints.destroy', { aiHint: id }))
              .then(() => {
                window.table
                  .row((_, data) => data.id === id)
                  .remove()
                  .draw(false);
                toastHelpers.showSuccessToast(__('AI hint deleted'));
              })
              .catch(() =>
                toastHelpers.showErrorToast(__('Error while deleting AI hint')),
              );
          },
        );
      });
    },
  });

  $('input[name=table_filter_active]').on('change', function () {
    window.table.column(4).search(this.value).draw();
  });

  $('#table_filter_search_text').on('input', function () {
    window.table.search($(this).val()).draw();
  });

  $('#button-new-hint').on('click', function () {
    app.showNewModal();
  });
};

window.axios
  .get(route('api.v1.ai-hints.index'))
  .then((response) => {
    buildTable(Array.isArray(response.data) ? response.data : []);
  })
  .catch(() => {
    toastHelpers.showErrorToast(__('Error while loading AI hints'));
    buildTable([]);
  });
