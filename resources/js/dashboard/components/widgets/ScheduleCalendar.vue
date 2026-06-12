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
      <Calendar
        ref="calendar"
        class="custom-calendar"
        :masks="masks"
        :attributes="transactions"
        :first-day-of-week="2"
        :min-date="minDate"
        :max-date="maxDate"
        disable-page-swipe
        expanded
        trim-weeks
        :locale="language"
        @transition-start="onCalendarTransitionStart"
        @update:pages="handlePagesUpdate"
      >
        <template #day-content="{ day, attributes }">
          <div>
            <span class="day-label text-sm">{{ day.day }}</span>
            <div class="vc-day-custom-content">
              <button
                v-for="item in attributes"
                :key="item.key"
                type="button"
                class="btn btn-link p-0 schedule-calendar-trigger"
                @mouseenter="onTransactionTriggerEnter($event, item.customData)"
                @mouseleave="onTransactionTriggerLeave"
                @focus="onTransactionTriggerEnter($event, item.customData)"
                @blur="onTransactionTriggerLeave"
              >
                <i :class="getTransactionIconClasses(item.customData)"></i>
              </button>
            </div>
          </div>
        </template>
      </Calendar>
    </div>
  </div>
</template>

<script>
  import {
    escapeHtml,
    escapeHtmlWithLineBreaks,
    getTransactionTypeConfig,
  } from '@/shared/lib/helpers';
  import { __, toFormattedCurrency } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';
  import { Calendar } from 'v-calendar';

  export default {
    components: {
      Calendar,
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
        transactions: [],
        masks: {
          weekdays: 'WWW',
        },
        minDate: null,
        maxDate: null,
        activePopover: null,
        activePopoverTrigger: null,
        popoverHideTimer: null,
        popoverHideDelayMs: 1000,
        skipInstanceBusy: false,
        visiblePage: null,
      };
    },

    created() {
      this.loadTransactions();
      window.addEventListener(
        'transaction-created',
        this.handleTransactionCreated,
      );
    },

    mounted() {
      document.addEventListener('click', this.onPopoverActionClick);
    },

    beforeUnmount() {
      document.removeEventListener('click', this.onPopoverActionClick);
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
          return ['fa', 'fa-line-chart', 'text-primary'];
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
      clearPopoverHideTimer() {
        if (!this.popoverHideTimer) {
          return;
        }

        clearTimeout(this.popoverHideTimer);
        this.popoverHideTimer = null;
      },
      schedulePopoverHide() {
        this.clearPopoverHideTimer();
        this.popoverHideTimer = setTimeout(() => {
          this.hideActivePopover();
        }, this.popoverHideDelayMs);
      },
      hideActivePopover() {
        this.clearPopoverHideTimer();

        if (!this.activePopover) {
          return;
        }

        const tip = this.getPopoverTipElement();
        if (tip) {
          tip.removeEventListener('mouseenter', this.clearPopoverHideTimer);
          tip.removeEventListener('mouseleave', this.schedulePopoverHide);
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
      showPopover(event, transaction) {
        if (!event.currentTarget || !transaction) {
          return;
        }

        const triggerElement = event.currentTarget;
        const shouldRecreatePopover =
          !this.activePopover || this.activePopoverTrigger !== triggerElement;

        this.clearPopoverHideTimer();

        if (shouldRecreatePopover) {
          this.disposeActivePopover();

          this.activePopover = new window.bootstrap.Popover(triggerElement, {
            container: 'body',
            content: this.getPopoverContent(transaction),
            customClass: 'schedule-calendar-popover',
            html: true,
            placement: 'auto',
            popperConfig(defaultBsPopperConfig) {
              return {
                ...defaultBsPopperConfig,
                modifiers: [
                  ...(defaultBsPopperConfig?.modifiers || []),
                  {
                    name: 'offset',
                    options: {
                      offset: [0, 12],
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
          tip.addEventListener('mouseenter', this.clearPopoverHideTimer);
          tip.addEventListener('mouseleave', this.schedulePopoverHide);
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
      onTransactionTriggerEnter(event, transaction) {
        this.showPopover(event, transaction);
      },
      onTransactionTriggerLeave() {
        this.schedulePopoverHide();
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
          date: transaction.transaction_schedule?.next_date,
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
        const button = event.target.closest('[data-schedule-calendar-action]');
        if (!button) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        const action = button.dataset.scheduleCalendarAction;
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
      handlePagesUpdate(pages) {
        if (!Array.isArray(pages) || pages.length === 0) {
          return;
        }

        const firstPage = pages[0];
        if (!firstPage?.month || !firstPage?.year) {
          return;
        }

        this.visiblePage = {
          month: firstPage.month,
          year: firstPage.year,
        };
      },
      async restoreVisiblePage() {
        if (!this.visiblePage || !this.$refs.calendar?.move) {
          return;
        }

        await this.$nextTick();

        try {
          await this.$refs.calendar.move(this.visiblePage, {
            force: true,
            position: 1,
            transition: 'none',
          });
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
                dates: new Date(transaction.transaction_schedule.next_date),
              };
            });

          this.updateCalendarRange();
          await this.restoreVisiblePage();
        } finally {
          this.busy = false;
        }
      },
      handleTransactionCreated() {
        this.loadTransactions();
      },
      onCalendarTransitionStart() {
        this.disposeActivePopover();
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
  .custom-calendar.vc-container {
    border-radius: 0;
    max-width: 100%;
  }

  .custom-calendar .vc-header {
    margin-bottom: 10px;
  }

  .custom-calendar .vc-weeks {
    padding: 0;
  }

  .custom-calendar .vc-weekday {
    background-color: #f8fafc;
    border-bottom: 1px solid #eaeaea;
    border-top: 1px solid #eaeaea;
    padding: 5px 0;
  }

  .custom-calendar .vc-day {
    border: 1px solid #b8c2cc;
    padding: 0 5px 3px 5px;
    text-align: left;
    height: 65px;
    min-width: 45px;
    background-color: white;
  }

  .custom-calendar .vc-day-custom-content {
    line-height: normal;
  }

  .schedule-calendar-trigger {
    margin: 0 1px;
    text-decoration: none;
  }

  .schedule-calendar-trigger:focus {
    box-shadow: none;
  }

  .schedule-calendar-popover {
    --bs-popover-bg: #1f2937;
    --bs-popover-body-bg: #1f2937;
    --bs-popover-border-color: transparent;
    --bs-popover-arrow-border: transparent;
    --cui-popover-bg: #1f2937;
    --cui-popover-body-bg: #1f2937;
    --cui-popover-border-color: transparent;
    --cui-popover-arrow-border: transparent;
    background-color: transparent;
  }

  .popover.schedule-calendar-popover {
    background-color: #1f2937;
    border-color: transparent;
  }

  .schedule-calendar-popover .popover-body {
    min-width: 230px;
    background-color: #1f2937;
    color: #f8fafc;
  }

  .schedule-calendar-popover .popover-arrow::before {
    display: none;
  }

  .popover.schedule-calendar-popover[data-popper-placement^='top']
    > .popover-arrow::after {
    border-top-color: #1f2937;
  }

  .popover.schedule-calendar-popover[data-popper-placement^='bottom']
    > .popover-arrow::after {
    border-bottom-color: #1f2937;
  }

  .popover.schedule-calendar-popover[data-popper-placement^='left']
    > .popover-arrow::after {
    border-left-color: #1f2937;
  }

  .popover.schedule-calendar-popover[data-popper-placement^='right']
    > .popover-arrow::after {
    border-right-color: #1f2937;
  }

  .schedule-calendar-popover-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
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
