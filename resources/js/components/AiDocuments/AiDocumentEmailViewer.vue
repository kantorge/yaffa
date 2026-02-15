<template>
  <div
    class="tab-pane fade"
    id="document-tab-email"
    role="tabpanel"
    aria-labelledby="nav-document-tab-email"
    tabindex="0"
  >
    <div v-if="!hasHtml && !hasText" class="text-muted">
      {{ __('No email content available') }}
    </div>
    <div v-else class="card mb-3">
      <div class="card-header" v-if="hasHtml && hasText">
        <ul class="nav nav-tabs card-header-tabs">
          <li class="nav-item">
            <button
              class="nav-link active"
              id="nav-email-tab-html"
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
              class="nav-link"
              id="nav-email-tab-text"
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
        <div class="tab-content" id="nav-tabContent">
          <div
            v-if="hasHtml"
            class="tab-pane fade"
            :class="{
              'show active': !hasText || hasHtml,
            }"
            id="email-tab-html"
            role="tabpanel"
            aria-labelledby="nav-email-tab-html"
            tabindex="0"
            v-html="receivedMail.html"
          ></div>
          <div
            v-else
            class="tab-pane fade show active"
            id="email-tab-html"
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
            class="tab-pane fade"
            :class="{ 'show active': !hasHtml }"
            id="email-tab-text"
            role="tabpanel"
            aria-labelledby="nav-email-tab-text"
            tabindex="0"
          >
            <pre>{{ receivedMail.text }}</pre>
          </div>
          <div
            v-else
            class="tab-pane fade"
            id="email-tab-text"
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
  import { __ } from '@/helpers';

  const props = defineProps({
    receivedMail: {
      type: Object,
      required: true,
    },
  });

  const hasHtml = computed(() => !!props.receivedMail?.html);
  const hasText = computed(() => !!props.receivedMail?.text);
</script>
