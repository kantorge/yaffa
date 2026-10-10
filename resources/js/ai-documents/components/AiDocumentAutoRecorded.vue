<template>
  <div id="ai-document-auto-recorded">
    <div class="card mb-3">
      <div
        class="card-header d-flex justify-content-between align-items-center"
      >
        <div class="card-title mb-0">
          {{ __('Recorded automatically, not yet confirmed') }}
        </div>
        <div v-if="origins.length > 0">
          <button
            type="button"
            class="btn btn-sm btn-outline-primary me-2"
            :disabled="selected.length === 0 || busy"
            dusk="confirm-selected"
            @click="confirmMany(selected)"
          >
            {{ __('Confirm selected') }}
          </button>
          <button
            type="button"
            class="btn btn-sm btn-primary"
            :disabled="busy"
            @click="confirmMany(origins.map((origin) => origin.id))"
          >
            {{ __('Confirm all') }}
          </button>
        </div>
      </div>
      <div class="card-body">
        <p class="text-muted small">
          {{
            __(
              'These transactions were recorded without your review. Confirm the ones that are right; reconciling a transaction confirms it too. Edit any that are not.',
            )
          }}
        </p>
        <div v-if="loading" class="text-muted">{{ __('Loading…') }}</div>
        <div v-else-if="origins.length === 0" class="text-muted">
          {{ __('Nothing to confirm.') }}
        </div>
        <div v-else class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead>
              <tr>
                <th></th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Payee') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
                <th>{{ __('Account') }}</th>
                <th>{{ __('Why it was recorded') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="origin in origins"
                :key="origin.id"
                :dusk="`origin-${origin.id}`"
              >
                <td>
                  <input
                    v-model="selected"
                    type="checkbox"
                    class="form-check-input"
                    :value="origin.id"
                  />
                </td>
                <td>{{ dateOf(origin.transaction) }}</td>
                <td>{{ payeeOf(origin.transaction) }}</td>
                <td class="text-end">{{ amountOf(origin.transaction) }}</td>
                <td>{{ accountOf(origin.transaction) }}</td>
                <td class="small">{{ origin.decision_reason }}</td>
                <td class="text-nowrap">
                  <a
                    class="btn btn-sm btn-outline-secondary me-1"
                    :href="transactionUrl(origin.transaction, 'show')"
                  >
                    {{ __('View') }}
                  </a>
                  <a
                    class="btn btn-sm btn-outline-secondary me-1"
                    :href="transactionUrl(origin.transaction, 'edit')"
                  >
                    {{ __('Edit') }}
                  </a>
                  <button
                    type="button"
                    class="btn btn-sm btn-success"
                    :disabled="busy"
                    dusk="confirm-origin"
                    @click="confirmMany([origin.id])"
                  >
                    {{ __('Confirm') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="conflicts.length > 0" class="card mb-3">
      <div class="card-header">
        <div class="card-title mb-0">
          {{ __('Conflicts') }}
        </div>
      </div>
      <div class="card-body">
        <p class="text-muted small">
          {{
            __(
              'A receipt arrived for these automatically recorded transactions, but its items differ. The transaction was left unchanged.',
            )
          }}
        </p>
        <div
          v-for="origin in conflicts"
          :key="origin.id"
          class="row border-top pt-3 mb-3"
          :dusk="`conflict-${origin.id}`"
        >
          <div class="col-md-5">
            <strong>{{ __('Recorded transaction') }}</strong>
            <div>
              {{ dateOf(origin.transaction) }} ·
              {{ payeeOf(origin.transaction) }} ·
              {{ amountOf(origin.transaction) }}
            </div>
            <ul class="small mb-0">
              <li
                v-for="item in origin.transaction.transaction_items"
                :key="item.id"
              >
                {{ item.category?.full_name ?? item.category?.name ?? '' }}
                {{ item.amount }}
              </li>
            </ul>
          </div>
          <div class="col-md-4">
            <strong>{{ __('Receipt') }}</strong>
            <div v-if="origin.ai_document">
              {{ origin.ai_document.date }} · {{ origin.ai_document.amount }}
            </div>
            <ul v-if="origin.ai_document" class="small mb-0">
              <li
                v-for="(item, index) in origin.ai_document.items"
                :key="index"
              >
                {{ item.description }} {{ item.amount }}
              </li>
            </ul>
          </div>
          <div class="col-md-3 text-md-end">
            <a
              class="btn btn-sm btn-outline-secondary mb-1"
              :href="transactionUrl(origin.transaction, 'edit')"
            >
              {{ __('Edit transaction') }}
            </a>
            <a
              v-if="origin.ai_document"
              class="btn btn-sm btn-outline-secondary mb-1"
              :href="route('ai-documents.show', origin.ai_document.id)"
            >
              {{ __('Open receipt') }}
            </a>
            <button
              v-if="origin.ai_document?.status === 'ready_for_review'"
              type="button"
              class="btn btn-sm btn-outline-danger mb-1"
              :disabled="busy"
              @click="dismissDocument(origin)"
            >
              {{ __('Dismiss receipt') }}
            </button>
            <button
              type="button"
              class="btn btn-sm btn-success mb-1"
              :disabled="busy"
              @click="resolveConflict(origin)"
            >
              {{ __('Mark as resolved') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
  import { onMounted, ref } from 'vue';
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';

  const emit = defineEmits(['changed']);

  const route = window.route;
  const locale = window.YAFFA.userSettings.locale || 'en';
  const origins = ref([]);
  const conflicts = ref([]);
  const selected = ref([]);
  const loading = ref(true);
  const busy = ref(false);

  const isDeposit = (transaction) => transaction.transaction_type === 'deposit';
  // A withdrawal goes from the account to the payee, a deposit the other way round
  const payeeEntity = (transaction) =>
    isDeposit(transaction)
      ? transaction.config.account_from
      : transaction.config.account_to;
  const accountEntity = (transaction) =>
    isDeposit(transaction)
      ? transaction.config.account_to
      : transaction.config.account_from;

  const dateOf = (transaction) =>
    transaction.date
      ? toFormattedDate(new Date(transaction.date), locale, null)
      : '';
  const payeeOf = (transaction) => payeeEntity(transaction)?.name ?? '';
  const accountOf = (transaction) => accountEntity(transaction)?.name ?? '';
  const amountOf = (transaction) =>
    toFormattedCurrency(
      transaction.config.amount_from,
      locale,
      accountEntity(transaction)?.config?.currency,
    );
  const transactionUrl = (transaction, action) =>
    route('transaction.open', { transaction: transaction.id, action });

  const load = async () => {
    loading.value = true;

    try {
      const [created, conflicting] = await Promise.all(
        ['created', 'conflicts_with'].map((relation) =>
          window.axios.get(route('api.v1.transaction-origins.index'), {
            params: { relation, reviewed: 0 },
          }),
        ),
      );
      origins.value = created.data;
      conflicts.value = conflicting.data;
      selected.value = [];
    } catch (error) {
      toastHelpers.showErrorToast(
        __(
          'Error while loading automatically recorded transactions: :errorMessage',
          {
            errorMessage: error.response?.data?.message || error.message,
          },
        ),
      );
    } finally {
      loading.value = false;
    }
  };

  const confirmMany = async (ids) => {
    busy.value = true;

    try {
      await window.axios.post(route('api.v1.transaction-origins.review-many'), {
        ids,
      });
      origins.value = origins.value.filter(
        (origin) => !ids.includes(origin.id),
      );
      selected.value = selected.value.filter((id) => !ids.includes(id));
      emit('changed', origins.value.length);
    } catch (error) {
      toastHelpers.showErrorToast(
        __('Error while confirming: :errorMessage', {
          errorMessage: error.response?.data?.message || error.message,
        }),
      );
    } finally {
      busy.value = false;
    }
  };

  const resolveConflict = async (origin) => {
    busy.value = true;

    try {
      await window.axios.post(
        route('api.v1.transaction-origins.review', { origin: origin.id }),
      );
      conflicts.value = conflicts.value.filter((row) => row.id !== origin.id);
    } catch (error) {
      toastHelpers.showErrorToast(
        __('Error while confirming: :errorMessage', {
          errorMessage: error.response?.data?.message || error.message,
        }),
      );
    } finally {
      busy.value = false;
    }
  };

  const dismissDocument = async (origin) => {
    busy.value = true;

    try {
      await window.axios.post(
        route('api.v1.documents.dismiss', {
          aiDocument: origin.ai_document.id,
        }),
      );
      origin.ai_document.status = 'dismissed';
      await resolveConflict(origin);
    } catch (error) {
      toastHelpers.showErrorToast(
        __('Error while dismissing document: :errorMessage', {
          errorMessage: error.response?.data?.message || error.message,
        }),
      );
      busy.value = false;
    }
  };

  onMounted(load);
</script>
