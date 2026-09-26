<template>
  <div class="json-node" :style="indentStyle">
    <details v-if="isExpandable" :open="depth < 1">
      <summary>
        <span v-if="label !== null" class="json-key">"{{ label }}"</span>
        <span v-if="label !== null" class="json-sep">: </span>
        <span class="json-type">{{ typeLabel }}</span>
      </summary>

      <div class="json-children">
        <AiDocumentJsonTreeNode
          v-for="(childValue, childKey) in iterableEntries"
          :key="String(childKey)"
          :value="childValue"
          :label="String(childKey)"
          :depth="depth + 1"
        />
      </div>
    </details>

    <div v-else class="json-leaf">
      <span v-if="label !== null" class="json-key">"{{ label }}"</span>
      <span v-if="label !== null" class="json-sep">: </span>
      <span :class="valueClass">{{ valueLabel }}</span>
    </div>
  </div>
</template>

<script setup>
  import { computed } from 'vue';

  const props = defineProps({
    value: {
      type: [Object, Array, String, Number, Boolean, null],
      default: null,
    },
    label: {
      type: String,
      default: null,
    },
    depth: {
      type: Number,
      default: 0,
    },
  });

  const isObject = computed(
    () =>
      props.value !== null &&
      typeof props.value === 'object' &&
      !Array.isArray(props.value),
  );

  const isArray = computed(() => Array.isArray(props.value));

  const isExpandable = computed(() => isObject.value || isArray.value);

  const iterableEntries = computed(() => {
    if (isArray.value) {
      return props.value;
    }

    if (isObject.value) {
      return props.value;
    }

    return [];
  });

  const typeLabel = computed(() => {
    if (isArray.value) {
      return `[${props.value.length}]`;
    }

    if (isObject.value) {
      return `{${Object.keys(props.value).length}}`;
    }

    return '';
  });

  const valueLabel = computed(() => {
    if (typeof props.value === 'string') {
      return `"${props.value}"`;
    }

    if (props.value === null) {
      return 'null';
    }

    return String(props.value);
  });

  const valueClass = computed(() => {
    if (typeof props.value === 'string') {
      return 'json-string';
    }

    if (typeof props.value === 'number') {
      return 'json-number';
    }

    if (typeof props.value === 'boolean') {
      return 'json-bool';
    }

    return 'json-null';
  });

  const indentStyle = computed(() => ({
    paddingLeft: `${props.depth * 0.5}rem`,
  }));
</script>

<style scoped>
  .json-node {
    font-family:
      Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.8rem;
    line-height: 1.45;
  }

  .json-key {
    color: var(--cui-body-color);
  }

  .json-sep {
    color: var(--cui-secondary-color);
  }

  .json-type {
    color: var(--cui-primary);
  }

  .json-string {
    color: #198754;
    word-break: break-word;
  }

  .json-number {
    color: #0d6efd;
  }

  .json-bool,
  .json-null {
    color: #6f42c1;
  }

  .json-children {
    margin-top: 0.35rem;
    border-left: 1px dashed var(--cui-border-color);
    padding-left: 0.5rem;
  }

  details > summary {
    cursor: pointer;
    user-select: none;
    list-style-position: inside;
    padding: 0.1rem 0;
  }

  .json-leaf {
    padding: 0.1rem 0;
  }
</style>
