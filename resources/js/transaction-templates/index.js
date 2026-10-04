import 'datatables.net-bs5';

import {
  booleanToTableIcon,
  genericDataTablesActionButton,
} from '@/shared/lib/datatable';
import { escapeHtml, getTransactionTypeConfig } from '@/shared/lib/helpers';
import {
  getDataTablesLanguageOptions,
  toFormattedDateTime,
} from '@/shared/lib/i18n';
import { confirmDelete } from '@/shared/lib/confirm';
import * as toastHelpers from '@/shared/lib/toast';
import { useTemplate } from './useTemplate';

const dataTableSelector = '#table';
const locale = window.YAFFA.userSettings.locale;

// The list page has no transaction modal, so a template is always used on the standalone form
const useButton = (id) =>
  `<button class="btn btn-xs btn-success data-use" data-id="${id}" type="button" title="${__('Use')}"><i class="fa fa-fw fa-play"></i></button> `;

const table = $(dataTableSelector).DataTable({
  language: getDataTablesLanguageOptions() || undefined,
  columns: [
    {
      data: 'name',
      title: __('Name'),
      render: (data, type) => (type === 'display' ? escapeHtml(data) : data),
    },
    {
      data: 'transaction_type',
      title: __('Type'),
      render: (data) => escapeHtml(getTransactionTypeConfig(data).label),
    },
    {
      data: 'payee_name',
      title: __('Payee'),
      defaultContent: '',
      render: (data, type) => (type === 'display' ? escapeHtml(data) : data),
    },
    {
      data: 'use_count',
      title: __('Times used'),
      className: 'text-center',
      type: 'num',
    },
    {
      data: 'last_used_at',
      title: __('Last used'),
      defaultContent: '',
      render: (data, type) =>
        type === 'display' ? toFormattedDateTime(data, locale, '') : data,
    },
    {
      data: 'is_featured',
      title: __('Show on dashboard'),
      className: 'text-center featuredIcon',
      render: (data, type) => booleanToTableIcon(data, type),
    },
    {
      data: 'id',
      title: __('Actions'),
      render: (data) =>
        useButton(data) +
        genericDataTablesActionButton(
          data,
          'edit',
          'transaction-templates.edit',
        ) +
        genericDataTablesActionButton(data, 'delete'),
      className: 'dt-nowrap',
      orderable: false,
      searchable: false,
    },
  ],
  order: [[3, 'desc']],
  deferRender: true,
  scrollCollapse: true,
  paging: false,
  processing: true,
});

axios
  .get(window.route('api.v1.transaction-templates.index'))
  .then((response) => table.rows.add(response.data).draw())
  .catch(() =>
    toastHelpers.showErrorToast(__('Unable to load the templates.')),
  );

$(dataTableSelector).on('click', '.data-use:not(.busy)', function () {
  const button = $(this).addClass('busy');
  useTemplate(Number(button.data('id')), { preferModal: false }).finally(() =>
    button.removeClass('busy'),
  );
});

$(dataTableSelector).on(
  'click',
  'td.featuredIcon > i:not(.inProgress)',
  function () {
    const row = table.row($(this).parents('tr'));

    $(this).removeClass().addClass('fa fa-spinner fa-spin inProgress');

    axios
      .patch(
        window.route('api.v1.transaction-templates.update', {
          template: row.data().id,
        }),
        { is_featured: !row.data().is_featured },
      )
      .then((response) => row.data(response.data.template))
      .catch(() =>
        toastHelpers.showErrorToast(__('Unable to update the template.')),
      )
      .finally(() => row.invalidate().draw(false));
  },
);

$(dataTableSelector).on('click', '.data-delete:not(.busy)', function () {
  const button = $(this);
  const row = table.row(button.parents('tr'));

  confirmDelete(__('Are you sure you want to delete this item?')).then(
    (result) => {
      if (!result.isConfirmed) {
        return;
      }

      button.addClass('busy');

      axios
        .delete(
          window.route('api.v1.transaction-templates.destroy', {
            template: row.data().id,
          }),
        )
        .then(() => {
          row.remove().draw();
          toastHelpers.showSuccessToast(__('Template deleted'));
        })
        .catch(() =>
          toastHelpers.showErrorToast(
            __('Error while trying to delete template'),
          ),
        )
        .finally(() => button.removeClass('busy'));
    },
  );
});
