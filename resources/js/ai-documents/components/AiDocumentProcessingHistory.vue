<template>
  <div class="timeline-wrapper">
    <div v-if="entries.length === 0" class="text-muted">
      {{ __('No processing history available') }}
    </div>

    <div v-else>
      <details
        v-for="(entry, index) in normalizedEntries"
        :key="`${entry.timestamp || 'no-time'}-${index}`"
        class="card mb-3 shadow-sm border-0 interaction-card"
      >
        <summary
          class="card-header d-flex justify-content-between align-items-center bg-body-tertiary"
        >
          <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-primary">{{ index + 1 }}</span>
            <strong>{{ formatStep(entry.step) }}</strong>
          </div>
          <small class="text-muted">{{
            formatTimestamp(entry.timestamp)
          }}</small>
        </summary>
        <div class="card-body">
          <details class="history-section" open>
            <summary class="fw-semibold">{{ __('Prompt') }}</summary>
            <div class="history-section-body">
              <ai-document-json-tree-node
                v-if="entry.promptContent.isJson"
                :value="entry.promptContent.value"
              />
              <pre v-else class="history-pre">{{
                entry.promptContent.text
              }}</pre>
            </div>
          </details>

          <details class="history-section mt-3" open>
            <summary class="fw-semibold">{{ __('Raw response') }}</summary>
            <div class="history-section-body">
              <ai-document-json-tree-node
                v-if="entry.responseContent.isJson"
                :value="entry.responseContent.value"
              />
              <pre v-else class="history-pre">{{
                entry.responseContent.text
              }}</pre>
            </div>
          </details>
        </div>
      </details>
    </div>
  </div>
</template>

<script setup>
  import { computed } from 'vue';
  import { __ } from '@/shared/lib/i18n';
  import AiDocumentJsonTreeNode from './AiDocumentJsonTreeNode.vue';

  const props = defineProps({
    entries: {
      type: Array,
      default: () => [],
    },
  });

  const normalizedEntries = computed(() =>
    props.entries.map((entry) => ({
      ...entry,
      promptContent: normalizeContent(entry?.prompt),
      responseContent: normalizeContent(entry?.response),
    })),
  );

  const looksLikeJson = (text) => {
    if (typeof text !== 'string') {
      return false;
    }

    const trimmed = text.trim();

    return (
      (trimmed.startsWith('{') && trimmed.endsWith('}')) ||
      (trimmed.startsWith('[') && trimmed.endsWith(']'))
    );
  };

  const normalizeContent = (content) => {
    if (content !== null && typeof content === 'object') {
      return {
        isJson: true,
        value: content,
        text: '',
      };
    }

    const text = typeof content === 'string' ? content : String(content ?? '');

    if (looksLikeJson(text)) {
      try {
        return {
          isJson: true,
          value: JSON.parse(text),
          text: '',
        };
      } catch {
        return {
          isJson: false,
          value: null,
          text,
        };
      }
    }

    return {
      isJson: false,
      value: null,
      text,
    };
  };

  const formatTimestamp = (timestamp) => {
    if (!timestamp) {
      return __('Unknown time');
    }

    return new Date(timestamp).toLocaleString(
      window.YAFFA.userSettings.locale || 'en',
    );
  };

  const formatStep = (step) => {
    if (!step) {
      return __('AI processing');
    }

    const map = {
      process_started: __('Processing started'),
      main_extraction: __('Main extraction'),
      account_matching: __('Account matching'),
      payee_matching: __('Payee matching'),
      investment_matching: __('Investment matching'),
      category_batch_matching: __('Category matching'),
    };

    return map[step] || step;
  };
</script>

<style scoped>
  .timeline-wrapper {
    max-width: 100%;
  }

  .interaction-card > summary {
    cursor: pointer;
    user-select: none;
    list-style-position: inside;
  }

  .interaction-card[open] > summary {
    border-bottom: 1px solid var(--cui-border-color);
  }

  .history-section {
    border: 1px solid var(--cui-border-color);
    border-radius: 0.5rem;
    background: var(--cui-body-bg);
  }

  .history-section > summary {
    cursor: pointer;
    padding: 0.6rem 0.8rem;
    border-bottom: 1px solid transparent;
    user-select: none;
  }

  .history-section[open] > summary {
    border-bottom-color: var(--cui-border-color);
  }

  .history-section-body {
    padding: 0.75rem;
    background: var(--cui-tertiary-bg);
    border-radius: 0 0 0.5rem 0.5rem;
  }

  .history-pre {
    margin: 0;
    max-height: 380px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 0.8rem;
    background: transparent;
  }
</style>
