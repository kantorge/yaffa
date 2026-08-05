<template>
  <div id="widgetScheduleCalendar" class="card mb-4">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('widget.scheduleCalendar.cardTitle') }}
      </div>
      <div>
        <button
          type="button"
          class="btn-close"
          aria-label="Close"
          :disabled="busy"
          @click="hide"
        ></button>
      </div>
    </div>
    <div class="card-body">
      <p v-if="busy" aria-hidden="true" class="placeholder-glow">
        <span class="placeholder col-12"></span>
      </p>
      <p v-else-if="loadError" class="text-danger mb-0">
        {{ __('widget.scheduleCalendar.loadError') }}
      </p>
      <p v-else-if="transactions.length === 0" class="text-muted mb-0">
        {{ __('No data available') }}
      </p>
      <FullCalendar v-else ref="calendar" class="custom-calendar" :options="calendarOptions">
        <template #eventContent="arg">
          <i
            :class="getTransactionIconClasses(arg.event.extendedProps.transaction)"
          ></i>
        </template>
      </FullCalendar>
    </div>
  </div>
</template>

<script>
  import {
    escapeHtml,
    escapeHtmlWithLineBreaks,
    getTransactionTypeConfig,
    parseIsoDate,
  } from '@/shared/lib/helpers';
  import { __, toFormattedCurrency } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';
  import FullCalendar from '@fullcalendar/vue3';
  import dayGridPlugin from '@fullcalendar/daygrid';
  import frLocale from '@fullcalendar/core/locales/fr';
  import huLocale from '@fullcalendar/core/locales/hu';
  import plLocale from '@fullcalendar/core/locales/pl';

  const calendarLocales = [frLocale, huLocale, plLocale];

  export default {
    components: {
      FullCalendar,
    },

    props: {
      locale: {
        type: String,
        default: window.YAFFA.userSettings.locale,
      },
      language: {
        type: String,
        default: window.YAFFA.userSettings.language,
      },
    },

    data() {
      return {
        busy: false,
        loadError: false,
        transactions: [],
        minDate: null,
        maxDate: null,
        activePopover: null,
        activePopoverTrigger: null,
        skipInstanceBusy: false,
        visiblePage: null,
      };
    },

    computed: {
      // One FullCalendar "event" per matching transaction; FullCalendar
      // groups these by day itself, so no manual per-day aggregation is
      // needed the way the old day-content slot required.
      calendarEvents() {
        return this.transactions.map((item) => ({
          id: String(item.key),
          start: item.dates,
          allDay: true,
          extendedProps: {
            transaction: item.customData,
          },
        }));
      },

      // FullCalendar's validRange.end is exclusive, while maxDate (see
      // updateCalendarRange) is the last calendar day of the target month -
      // push the boundary one day later so that day stays reachable.
      validRange() {
        if (!this.minDate || !this.maxDate) {
          return undefined;
        }

        return {
          start: this.minDate,
          end: new Date(
            this.maxDate.getFullYear(),
            this.maxDate.getMonth(),
            this.maxDate.getDate() + 1,
          ),
        };
      },

      calendarOptions() {
        return {
          plugins: [dayGridPlugin],
          initialView: 'dayGridMonth',
          headerToolbar: {
            left: 'prev',
            center: 'title',
            right: 'next',
          },
          firstDay: 1,
          height: 'auto',
          fixedWeekCount: false,
          dayMaxEvents: false,
          dayHeaderFormat: { weekday: 'short' },
          locale: this.language,
          locales: calendarLocales,
          events: this.calendarEvents,
          validRange: this.validRange,
          datesSet: this.onDatesSet,
          eventClick: this.onEventClick,
          eventDidMount: this.onEventDidMount,
        };
      },
    },

    created() {
      this.loadTransactions();
      window.addEventListener(
        'transaction-created',
        this.handleTransactionCreated,
      );
    },

    beforeUnmount() {
      window.removeEventListener(
        'transaction-created',
        this.handleTransactionCreated,
      );
      this.disposeActivePopover();
    },

    methods: {
      getTransactionIconClasses(transaction) {
        if (!transaction) {
          return [];
        }

        const typeConfig = getTransactionTypeConfig(
          transaction.transaction_type,
        );

        if (typeConfig.category === 'standard') {
          if (transaction.transaction_type === 'withdrawal') {
            return ['fa', 'fa-circle-minus', 'text-danger'];
          }
          if (transaction.transaction_type === 'deposit') {
            return ['fa', 'fa-circle-plus', 'text-success'];
          }
          if (transaction.transaction_type === 'transfer') {
            return ['fa', 'fa-exchange-alt', 'text-primary'];
          }
        }

        if (typeConfig.category === 'investment') {
          return ['fa', 'fa-chart-line', 'text-primary'];
        }

        return ['fa', 'fa-circle', 'text-muted'];
      },
      getTransactionById(id) {
        const transactionId = Number(id);

        return (
          this.transactions.find(
            (attribute) => Number(attribute.customData?.id) === transactionId,
          )?.customData || null
        );
      },
      getTransactionLabel(transaction) {
        if (!transaction) {
          return '';
        }

        if (transaction.config_type === 'standard') {
          const type =
            transaction.transaction_type.charAt(0).toUpperCase() +
            transaction.transaction_type.slice(1);

          return this.__('widget.scheduleCalendar.transactionLabel', {
            type: __(type),
            amount: toFormattedCurrency(
              transaction.config.amount_to,
              this.locale,
              transaction.transaction_currency,
            ),
            fromAccount: transaction.config.account_from.name,
            toAccount: transaction.config.account_to.name,
          });
        }

        if (transaction.config_type === 'investment') {
          const typeConfig = getTransactionTypeConfig(
            transaction.transaction_type,
          );
          const investmentName = transaction.config?.investment?.name;
          const accountName = transaction.config?.account?.name;
          const quantity = transaction.config?.quantity;

          let label = `${this.__(typeConfig.label)}: ${investmentName || this.__('N/A')}`;

          if (accountName) {
            label += `\n${this.__('Account')}: ${accountName}`;
          }

          if (quantity !== null && quantity !== undefined) {
            label += `\n${this.__('Quantity')}: ${Number(quantity).toLocaleString(this.locale)}`;
          }

          return label;
        }

        return '';
      },
      getPopoverContent(transaction) {
        const label = escapeHtmlWithLineBreaks(
          this.getTransactionLabel(transaction),
        );

        return `
      <div class="schedule-calendar-popover-content">
        <div class="schedule-calendar-popover-header">
          <button
            type="button"
            class="btn-close btn-close-white schedule-calendar-popover-close"
            data-schedule-calendar-action="close"
            title="${escapeHtml(this.__('Close'))}"
            aria-label="${escapeHtml(this.__('Close'))}"
          ></button>
        </div>
        <div class="schedule-calendar-popover-label">${label}</div>
        <div class="schedule-calendar-popover-actions">
          <button
            type="button"
            class="btn btn-sm btn-warning"
            data-schedule-calendar-action="skip"
            data-transaction-id="${transaction.id}"
            title="${escapeHtml(this.__('Skip schedule instance'))}"
          >
            <i class="fa fa-fw fa-fast-forward"></i>
          </button>
          <button
            type="button"
            class="btn btn-sm btn-success"
            data-schedule-calendar-action="enter"
            data-transaction-id="${transaction.id}"
            title="${escapeHtml(this.__('Enter schedule instance'))}"
          >
            <i class="fa fa-fw fa-pencil"></i>
          </button>
        </div>
      </div>
    `;
      },
      hideActivePopover() {
        if (!this.activePopover) {
          return;
        }

        const tip = this.getPopoverTipElement();
        if (tip) {
          tip.removeEventListener('click', this.onPopoverActionClick);
        }

        this.activePopover.hide();
      },
      disposeActivePopover() {
        this.hideActivePopover();

        if (!this.activePopover) {
          return;
        }

        this.activePopover.dispose();
        this.activePopover = null;
        this.activePopoverTrigger = null;
      },
      showPopover(triggerElement, transaction) {
        if (!triggerElement || !transaction) {
          return;
        }

        const shouldRecreatePopover =
          !this.activePopover || this.activePopoverTrigger !== triggerElement;

        if (shouldRecreatePopover) {
          this.disposeActivePopover();

          this.activePopover = new window.bootstrap.Popover(triggerElement, {
            container: 'body',
            content: this.getPopoverContent(transaction),
            customClass: 'schedule-calendar-popover',
            html: true,
            placement: 'top',
            fallbackPlacements: ['top', 'right', 'left', 'bottom'],
            popperConfig(defaultBsPopperConfig) {
              return {
                ...defaultBsPopperConfig,
                modifiers: [
                  ...(defaultBsPopperConfig?.modifiers || []),
                  {
                    name: 'offset',
                    options: {
                      offset: [0, 8],
                    },
                  },
                ],
              };
            },
            sanitize: false,
            trigger: 'manual',
          });
          this.activePopoverTrigger = triggerElement;
        }

        this.activePopover.show();

        const tip = this.getPopoverTipElement();
        if (tip) {
          tip.addEventListener('click', this.onPopoverActionClick);
        }
      },
      getPopoverTipElement() {
        if (!this.activePopover) {
          return null;
        }

        if (typeof this.activePopover.getTipElement === 'function') {
          return this.activePopover.getTipElement();
        }

        return this.activePopover.tip || null;
      },
      // FullCalendar's own click handling for the event wrapper - the
      // eventContent slot renders a plain, non-interactive icon (see template)
      // so there's no nested interactive element inside FullCalendar's own
      // <a> wrapper.
      onEventClick(clickInfo) {
        this.showPopover(
          clickInfo.el,
          clickInfo.event.extendedProps.transaction,
        );
      },
      // Gives the FullCalendar-rendered event wrapper a single accessible
      // label and keyboard operability, since FullCalendar itself doesn't
      // add either by default.
      onEventDidMount(info) {
        const transaction = info.event.extendedProps.transaction;
        if (!transaction) {
          return;
        }

        info.el.setAttribute('aria-label', this.getTransactionLabel(transaction));
        info.el.setAttribute('tabindex', '0');
        info.el.addEventListener('keydown', (event) => {
          if (event.key !== 'Enter' && event.key !== ' ') {
            return;
          }

          event.preventDefault();
          this.showPopover(info.el, transaction);
        });
      },
      async skipInstance(transactionId, buttonElement = null) {
        if (this.skipInstanceBusy) {
          return;
        }

        this.skipInstanceBusy = true;
        const id = Number(transactionId);
        const originalButtonContent = buttonElement?.innerHTML || null;

        try {
          if (buttonElement) {
            buttonElement.disabled = true;
            buttonElement.innerHTML =
              '<i class="fa fa-fw fa-spinner fa-spin"></i>';
          }

          await axios.patch(
            this.route('api.v1.transactions.skip', {
              transaction: id,
            }),
          );

          toastHelpers.showSuccessToast(this.__('Schedule instance skipped.'));

          this.hideActivePopover();
          await this.loadTransactions();
        } catch (error) {
          toastHelpers.showErrorToast(
            error?.response?.data?.message ||
              error?.message ||
              this.__('Error skipping schedule instance.'),
          );
        } finally {
          if (buttonElement) {
            buttonElement.disabled = false;
            buttonElement.innerHTML = originalButtonContent;
          }

          this.skipInstanceBusy = false;
        }
      },
      enterInstance(transactionId) {
        const transaction = this.getTransactionById(transactionId);

        if (!transaction) {
          return;
        }

        const draft = {
          ...transaction,
          schedule: false,
          budget: false,
          date: parseIsoDate(transaction.transaction_schedule?.next_date),
        };

        const event = new CustomEvent('initiateEnterInstance', {
          detail: {
            transaction: draft,
          },
        });
        window.dispatchEvent(event);

        this.hideActivePopover();
      },
      onPopoverActionClick(event) {
        if (!(event.target instanceof Element)) {
          return;
        }

        const button = event.target.closest('[data-schedule-calendar-action]');
        if (!button) {
          return;
        }

        const tip = this.getPopoverTipElement();
        if (!tip || !tip.contains(button)) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        const action = button.dataset.scheduleCalendarAction;

        if (action === 'close') {
          this.hideActivePopover();
          return;
        }

        const transactionId = Number(button.dataset.transactionId);

        if (!transactionId) {
          return;
        }

        if (action === 'skip') {
          this.skipInstance(transactionId, button);
          return;
        }

        if (action === 'enter') {
          this.enterInstance(transactionId);
        }
      },
      // Fires on initial render and whenever the visible range changes
      // (navigation, or a reactive option update such as a new validRange) -
      // covers both the old @transition-start (popover cleanup) and
      // @update:pages (visible-page tracking) handlers.
      onDatesSet(dateInfo) {
        this.disposeActivePopover();

        const anchor = dateInfo?.view?.currentStart;
        if (!anchor) {
          return;
        }

        this.visiblePage = {
          month: anchor.getMonth() + 1,
          year: anchor.getFullYear(),
        };
      },
      async restoreVisiblePage() {
        if (!this.visiblePage) {
          return;
        }

        await this.$nextTick();

        const api = this.$refs.calendar?.getApi?.();
        if (!api) {
          return;
        }

        try {
          api.gotoDate(
            new Date(this.visiblePage.year, this.visiblePage.month - 1, 1),
          );
        } catch (_error) {
          // Ignore failed page restoration when the requested page is no longer valid.
        }
      },
      updateCalendarRange() {
        if (this.transactions.length > 1) {
          const minDate = this.transactions
            .map((transaction) => transaction.dates)
            .reduce(function (a, b) {
              return a < b ? a : b;
            });

          this.minDate = new Date(minDate.getFullYear(), minDate.getMonth(), 1);

          const maxDate = this.transactions
            .map((transaction) => transaction.dates)
            .reduce(function (a, b) {
              return a > b ? a : b;
            });

          this.maxDate = new Date(
            maxDate.getFullYear(),
            maxDate.getMonth() + 1,
            0,
          );
          return;
        }

        if (this.transactions.length === 1) {
          const date = new Date(this.transactions[0].dates);
          this.minDate = new Date(date.getFullYear(), date.getMonth(), 1);
          this.maxDate = new Date(date.getFullYear(), date.getMonth() + 1, 0);
          return;
        }

        const date = this.visiblePage
          ? new Date(this.visiblePage.year, this.visiblePage.month - 1, 1)
          : new Date();
        this.minDate = new Date(date.getFullYear(), date.getMonth(), 1);
        this.maxDate = new Date(date.getFullYear(), date.getMonth() + 1, 0);
      },
      async loadTransactions() {
        this.busy = true;
        this.loadError = false;
        this.disposeActivePopover();

        try {
          const response = await axios.get(
            '/api/v1/transactions/scheduled-items?type=schedule',
          );

          this.transactions = response.data.transactions
            .filter(
              (transaction) =>
                transaction.transaction_schedule &&
                transaction.transaction_schedule.next_date,
            )
            .map(function (transaction, index) {
              return {
                key: index + 1,
                customData: transaction,
                dates: parseIsoDate(transaction.transaction_schedule.next_date),
              };
            });

          this.updateCalendarRange();
          await this.restoreVisiblePage();
        } catch (error) {
          this.loadError = true;
          toastHelpers.showErrorToast(
            error?.response?.data?.message ||
              error?.message ||
              this.__('widget.scheduleCalendar.loadError'),
          );
        } finally {
          this.busy = false;
        }
      },
      handleTransactionCreated() {
        this.loadTransactions();
      },
      __,
      toFormattedCurrency,
      hide() {
        this.disposeActivePopover();
        $('#widgetScheduleCalendar').hide();
      },
    },
  };
</script>

<style>
  .custom-calendar.fc {
    max-width: 100%;
  }

  .custom-calendar .fc-toolbar {
    margin-bottom: 10px;
  }

  .custom-calendar .fc-toolbar-title {
    font-size: 1.1rem;
  }

  /* Reskin FullCalendar's default blue prev/next buttons to match the
     app's neutral Bootstrap outline buttons, via its own CSS custom
     properties rather than fighting .fc-button specificity. */
  .custom-calendar {
    --fc-button-bg-color: transparent;
    --fc-button-border-color: var(--cui-border-color, #b8c2cc);
    --fc-button-text-color: var(--cui-body-color, #212529);
    --fc-button-hover-bg-color: var(--cui-tertiary-bg, #f8fafc);
    --fc-button-hover-border-color: var(--cui-border-color, #b8c2cc);
    --fc-button-active-bg-color: var(--cui-secondary-bg, #eaeaea);
    --fc-button-active-border-color: var(--cui-border-color, #b8c2cc);
  }

  /* Disabled prev/next (at the edge of validRange) reads as plainly
     inactive - no border/box, faint icon - instead of just a slightly
     lighter copy of the enabled button. */
  .custom-calendar .fc-button:disabled {
    opacity: 0.3;
    background-color: transparent;
    border-color: transparent;
    cursor: default;
  }

  /* Day numbers render as <a> tags with no click handler attached - style
     them as plain text instead of the app's default link color/underline. */
  .custom-calendar .fc-daygrid-day-number {
    color: inherit;
    text-decoration: none;
    cursor: default;
  }

  .custom-calendar .fc-col-header-cell {
    background-color: #f8fafc;
    border-top: 1px solid #eaeaea;
    border-bottom: 1px solid #eaeaea;
  }

  /* Also an <a> with no click handler attached - same treatment as
     fc-daygrid-day-number below. */
  .custom-calendar .fc-col-header-cell-cushion {
    display: block;
    padding: 5px 0;
    color: inherit;
    text-decoration: none;
    cursor: default;
  }

  [data-coreui-theme="dark"] .custom-calendar .fc-col-header-cell {
    background-color: var(--cui-secondary-bg);
    border-color: var(--cui-border-color);
  }

  .custom-calendar .fc-daygrid-day {
    border-color: #b8c2cc;
  }

  [data-coreui-theme="dark"] .custom-calendar .fc-daygrid-day {
    border-color: var(--cui-border-color);
  }

  .custom-calendar .fc-daygrid-day-frame {
    min-height: 65px;
    padding: 0 5px 3px 5px;
    background-color: white;
  }

  [data-coreui-theme="dark"] .custom-calendar .fc-daygrid-day-frame {
    background-color: var(--cui-body-bg);
  }

  /* Lay the day's transaction icons out in a row, like the old
     v-calendar day-content slot, instead of FullCalendar's default of
     one event per row. */
  .custom-calendar .fc-daygrid-day-events {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    line-height: normal;
    min-height: 0;
    margin: 0;
  }

  .custom-calendar .fc-daygrid-event-harness {
    width: auto;
    margin: 0;
  }

  /* FullCalendar wraps each event in its own <a> (href-less, so it has no
     default browser action); it - not a nested control - is the single
     interactive/focusable element, activated via eventClick/keydown. */
  .custom-calendar .fc-daygrid-event-harness .fc-event {
    background: none;
    border: none;
    padding: 0;
    margin: 0 1px;
    text-decoration: none;
    cursor: pointer;
  }

  .custom-calendar .fc-daygrid-event-harness .fc-event:focus {
    outline: 2px solid var(--cui-primary);
    outline-offset: 2px;
    box-shadow: none;
  }

  /* Light mode: intentionally dark tooltip for contrast against the white calendar */
  .schedule-calendar-popover {
    --schedule-popover-bg: #1f2937;
    --schedule-popover-color: #f8fafc;
    --schedule-popover-border: transparent;
    --bs-popover-bg: var(--schedule-popover-bg);
    --bs-popover-body-bg: var(--schedule-popover-bg);
    --bs-popover-border-color: var(--schedule-popover-border);
    --bs-popover-arrow-border: var(--schedule-popover-border);
    --cui-popover-bg: var(--schedule-popover-bg);
    --cui-popover-body-bg: var(--schedule-popover-bg);
    --cui-popover-border-color: var(--schedule-popover-border);
    --cui-popover-arrow-border: var(--schedule-popover-border);
    background-color: transparent;
  }

  /* Dark mode: flipped to light background for contrast against the dark page */
  [data-coreui-theme="dark"] .schedule-calendar-popover {
    --schedule-popover-bg: #f8fafc;
    --schedule-popover-color: #1e293b;
    --schedule-popover-border: rgba(0, 0, 0, 0.15);
  }

  .popover.schedule-calendar-popover {
    background-color: var(--schedule-popover-bg);
    border-color: var(--schedule-popover-border);
  }

  .schedule-calendar-popover .popover-body {
    min-width: 230px;
    background-color: var(--schedule-popover-bg);
    color: var(--schedule-popover-color);
  }

  .schedule-calendar-popover .popover-arrow::before {
    display: none;
  }

  /* In dark mode the close button uses btn-close-white (invert filter) — neutralise
     it so the icon appears dark against the light popover background. */
  [data-coreui-theme="dark"] .schedule-calendar-popover .btn-close-white {
    filter: none;
  }

  .popover.schedule-calendar-popover[data-popper-placement^='top']
    > .popover-arrow::after {
    border-top-color: var(--schedule-popover-bg);
  }

  .popover.schedule-calendar-popover[data-popper-placement^='bottom']
    > .popover-arrow::after {
    border-bottom-color: var(--schedule-popover-bg);
  }

  .popover.schedule-calendar-popover[data-popper-placement^='left']
    > .popover-arrow::after {
    border-left-color: var(--schedule-popover-bg);
  }

  .popover.schedule-calendar-popover[data-popper-placement^='right']
    > .popover-arrow::after {
    border-right-color: var(--schedule-popover-bg);
  }

  .schedule-calendar-popover-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .schedule-calendar-popover-header {
    display: flex;
    justify-content: flex-end;
  }

  .schedule-calendar-popover-close {
    transform: scale(0.82);
    transform-origin: top right;
    opacity: 0.65;
  }

  .schedule-calendar-popover-close:hover {
    opacity: 0.85;
  }

  .schedule-calendar-popover-label {
    white-space: normal;
  }

  .schedule-calendar-popover-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
  }
</style>
