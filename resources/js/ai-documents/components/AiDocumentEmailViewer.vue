<template>
  <div
    id="document-tab-email"
    class="tab-pane fade"
    role="tabpanel"
    aria-labelledby="nav-document-tab-email"
    tabindex="0"
  >
    <div v-if="!hasHtml && !hasText" class="text-muted">
      {{ __('No email content available') }}
    </div>
    <div v-else class="card mb-3">
      <div v-if="hasHtml && hasText" class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
          <li class="nav-item">
            <button
              id="nav-email-tab-html"
              class="nav-link active"
              data-coreui-toggle="tab"
              data-coreui-target="#email-tab-html"
              type="button"
              role="tab"
              aria-controls="email-tab-html"
              aria-selected="true"
            >
              {{ __('HTML view') }}
            </button>
          </li>
          <li class="nav-item">
            <button
              id="nav-email-tab-text"
              class="nav-link"
              data-coreui-toggle="tab"
              data-coreui-target="#email-tab-text"
              type="button"
              role="tab"
              aria-controls="email-tab-text"
              aria-selected="false"
            >
              {{ __('Text view') }}
            </button>
          </li>
        </ul>
      </div>
      <div class="card-body">
        <div id="nav-tabContent" class="tab-content">
          <div
            v-if="hasHtml"
            id="email-tab-html"
            class="tab-pane fade"
            :class="{
              'show active': !hasText || hasHtml,
            }"
            role="tabpanel"
            aria-labelledby="nav-email-tab-html"
            tabindex="0"
            v-html="sanitizedHtml"
          ></div>
          <div
            v-else
            id="email-tab-html"
            class="tab-pane fade show active"
            role="tabpanel"
            aria-labelledby="nav-email-tab-html"
            tabindex="0"
          >
            <div class="text-muted">
              {{ __('HTML content not available') }}
            </div>
          </div>
          <div
            v-if="hasText"
            id="email-tab-text"
            class="tab-pane fade"
            :class="{ 'show active': !hasHtml }"
            role="tabpanel"
            aria-labelledby="nav-email-tab-text"
            tabindex="0"
          >
            <pre>{{ receivedMail.text }}</pre>
          </div>
          <div
            v-else
            id="email-tab-text"
            class="tab-pane fade"
            role="tabpanel"
            aria-labelledby="nav-email-tab-text"
            tabindex="0"
          >
            <div class="text-muted">
              {{ __('Text content not available') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
  import { computed } from 'vue';
  import DOMPurify from 'dompurify';
  import { __ } from '@/shared/lib/i18n';

  const props = defineProps({
    receivedMail: {
      type: Object,
      required: true,
    },
  });

  const hasHtml = computed(() => !!props.receivedMail?.html);
  const hasText = computed(() => !!props.receivedMail?.text);
  const sanitizedHtml = computed(() =>
    DOMPurify.sanitize(props.receivedMail?.html ?? ''),
  );
</script>
