<template>
  <div>
    <!-- Wizard header -->
    <div class="mb-4">
      <h6 class="mb-3 fw-bold">
        {{
          isEditMode
            ? __('Edit CSV import profile')
            : __('New CSV import profile')
        }}
      </h6>
      <div class="d-flex align-items-start">
        <template v-for="n in 4" :key="n">
          <div class="d-flex flex-column align-items-center wizard-step-item">
            <div class="wizard-step-circle" :class="stepCircleClass(n)">
              <i v-if="n < currentStep" class="fa fa-check"></i>
              <span v-else>{{ n }}</span>
            </div>
            <div
              class="wizard-step-label text-center"
              :class="stepLabelClass(n)"
            >
              {{ stepLabels[n - 1] }}
            </div>
          </div>
          <div
            v-if="n < 4"
            class="wizard-step-connector"
            :class="n < currentStep ? 'connector-done' : 'connector-pending'"
          ></div>
        </template>
      </div>
    </div>

    <!-- General error -->
    <div
      v-if="generalError"
      class="alert alert-danger alert-dismissible mb-3 small"
      role="alert"
    >
      {{ generalError }}
      <button
        type="button"
        class="btn-close"
        @click="generalError = null"
      ></button>
    </div>

    <!-- ────────────────────────────────────────────────── -->
    <!-- Step 1: File selection and auto-detection          -->
    <!-- ────────────────────────────────────────────────── -->
    <div v-if="currentStep === 1">
      <div class="mb-3">
        <label class="form-label">
          {{ __('Select a CSV file to analyse') }} *
        </label>
        <input
          ref="fileInput"
          type="file"
          class="form-control"
          accept=".csv,.txt"
          :disabled="aiSuggesting"
          @change="onFileChange"
        />
        <div class="form-text">
          {{
            __(
              'The file is read locally in your browser. No data is uploaded during this step.',
            )
          }}
        </div>
      </div>

      <template v-if="sampleFile !== null && headers.length > 0">
        <div class="d-flex gap-2 mb-2 flex-wrap align-items-center">
          <span class="border rounded px-2 py-1 bg-body-secondary">
            <span class="text-body-secondary">{{ __('Delimiter') }}</span>
            <code class="ms-1 fw-semibold text-primary">{{
              delimiterLabel(detectedDelimiter)
            }}</code>
          </span>
          <span class="border rounded px-2 py-1 bg-body-secondary">
            <i
              :class="
                detectedHasHeader
                  ? 'fa fa-check text-success'
                  : 'fa fa-xmark text-secondary'
              "
              class="me-1"
            ></i>
            <span :class="detectedHasHeader ? 'text-success' : 'text-body'">
              {{ detectedHasHeader ? __('Header row') : __('No header row') }}
            </span>
          </span>
          <span class="border rounded px-2 py-1 bg-body-secondary">
            <strong class="text-primary">{{ headers.length }}</strong>
            <span class="text-body-secondary ms-1">{{ __('columns') }}</span>
          </span>
        </div>
        <div class="mb-2 mt-4 fw-bold">
          {{
            __('Preview (first :count data rows):', { count: previewRowCount })
          }}
        </div>
        <CsvPreviewTable :headers="displayHeaders" :data-rows="previewRows" />

        <!-- AI suggestion (inline, Step 1 only) -->
        <div v-if="hasAiProvider" class="mt-3">
          <div v-if="!showAiPanel && !aiSuggesting">
            <button
              type="button"
              class="btn btn-sm btn-outline-info"
              @click="showAiPanel = true"
            >
              <i class="fa fa-wand-magic-sparkles me-1"></i
              >{{ __('Suggest with AI') }}
            </button>
            <span class="ms-2 small text-muted">{{
              __('— or click Next to configure manually')
            }}</span>
          </div>

          <div
            v-if="showAiPanel || aiSuggesting"
            class="border rounded p-3 bg-body-secondary"
          >
            <div class="alert alert-info small mb-2 py-2">
              <i class="fa fa-info-circle me-1"></i>
              {{
                __(
                  'The first 10 rows of your file will be sent to your configured AI provider using your API key.',
                )
              }}
            </div>

            <div v-if="accountId" class="form-text mb-2">
              <i class="fa fa-info-circle me-1"></i>
              {{
                __(
                  'Account context will be included in the AI prompt as a hint.',
                )
              }}
            </div>

            <div v-if="aiError" class="alert alert-danger small py-2 mb-2">
              {{ aiError }}
            </div>

            <div class="d-flex gap-2">
              <button
                type="button"
                class="btn btn-sm btn-info"
                :disabled="aiSuggesting"
                @click="requestAiSuggestion"
              >
                <span
                  v-if="aiSuggesting"
                  class="spinner-border spinner-border-sm me-1"
                ></span>
                {{
                  aiSuggesting
                    ? __('Requesting suggestion…')
                    : __('Confirm and send')
                }}
              </button>
              <button
                v-if="!aiSuggesting"
                type="button"
                class="btn btn-sm btn-outline-secondary"
                @click="showAiPanel = false"
              >
                {{ __('Cancel') }}
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ────────────────────────────────────────────────── -->
    <!-- Step 2: Parser settings                           -->
    <!-- ────────────────────────────────────────────────── -->
    <div v-if="currentStep === 2">
      <!-- Row 1: name + format controls -->
      <div class="row g-2 mb-3">
        <div class="col-md-5">
          <label class="form-label fw-bold">{{ __('Profile name') }} *</label>
          <input
            v-model="profileName"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': validationErrors.profileName }"
            :placeholder="__('e.g. My Bank CSV')"
          />
          <div v-if="validationErrors.profileName" class="invalid-feedback">
            {{ validationErrors.profileName }}
          </div>
        </div>

        <!-- Delimiter -->
        <div class="col-md-4">
          <label class="form-label fw-bold">{{ __('Delimiter') }}</label>
          <select
            v-model="delimiterChoice"
            class="form-select"
            @change="onSettingsChange"
          >
            <option value=",">, ({{ __('comma') }})</option>
            <option value=";">; ({{ __('semicolon') }})</option>
            <option value="	">&#8677; ({{ __('tab') }})</option>
            <option value="|">| ({{ __('pipe') }})</option>
            <option value="__custom__">{{ __('Custom…') }}</option>
          </select>
          <input
            v-if="delimiterChoice === '__custom__'"
            v-model="customDelimiter"
            type="text"
            class="form-control mt-1 font-monospace"
            maxlength="1"
            :placeholder="__('Single character')"
            @input="onSettingsChange"
          />
        </div>

        <!-- Has header row -->
        <div class="col-md-3">
          <label class="form-label fw-bold" for="wiz-has-header">
            {{ __('Has header row') }}
          </label>
          <div class="form-check form-switch form-check-lg mt-1">
            <input
              id="wiz-has-header"
              v-model="hasHeaderRow"
              type="checkbox"
              role="switch"
              class="form-check-input"
              @change="onSettingsChange"
            />
          </div>
        </div>
      </div>

      <!-- Row 2: numeric parsing controls -->
      <div class="row g-2 mb-3">
        <!-- Decimal separator -->
        <div class="col-md-3">
          <label class="form-label fw-bold">{{
            __('Decimal separator')
          }}</label>
          <div class="form-check mt-1">
            <input
              id="dec-dot"
              v-model="decimalSeparator"
              type="radio"
              value="."
              class="form-check-input"
              @change="onSettingsChange"
            />
            <label for="dec-dot" class="form-check-label font-monospace"
              >. ({{ __('dot') }})</label
            >
          </div>
          <div class="form-check mt-1">
            <input
              id="dec-comma"
              v-model="decimalSeparator"
              type="radio"
              value=","
              class="form-check-input"
              @change="onSettingsChange"
            />
            <label for="dec-comma" class="form-check-label font-monospace"
              >, ({{ __('comma') }})</label
            >
          </div>
        </div>

        <!-- Thousand separator -->
        <div class="col-md-4">
          <label class="form-label fw-bold">{{
            __('Thousand separator')
          }}</label>
          <div class="form-check mt-1">
            <input
              id="thou-space"
              v-model="thousandSeparator"
              type="radio"
              value=" "
              class="form-check-input"
              @change="onSettingsChange"
            />
            <label for="thou-space" class="form-check-label">{{
              __('Space')
            }}</label>
          </div>
          <div class="form-check mt-1">
            <input
              id="thou-dot"
              v-model="thousandSeparator"
              type="radio"
              value="."
              class="form-check-input"
              @change="onSettingsChange"
            />
            <label for="thou-dot" class="form-check-label font-monospace"
              >. ({{ __('dot') }})</label
            >
          </div>
          <div class="form-check mt-1">
            <input
              id="thou-comma"
              v-model="thousandSeparator"
              type="radio"
              value=","
              class="form-check-input"
              @change="onSettingsChange"
            />
            <label for="thou-comma" class="form-check-label font-monospace"
              >, ({{ __('comma') }})</label
            >
          </div>
          <div class="form-check mt-1">
            <input
              id="thou-none"
              v-model="thousandSeparator"
              type="radio"
              value=""
              class="form-check-input"
              @change="onSettingsChange"
            />
            <label for="thou-none" class="form-check-label">{{
              __('None')
            }}</label>
          </div>
        </div>

        <!-- Sign handling -->
        <div class="col-md-5">
          <label class="form-label fw-bold">{{ __('Sign handling') }}</label>
          <div class="form-check mt-1">
            <input
              id="sign-asis"
              v-model="signHandling"
              type="radio"
              value="as_is"
              class="form-check-input"
            />
            <label for="sign-asis" class="form-check-label">
              {{ __('As-is') }}
              <span class="text-muted"
                >— {{ __('use the parsed signed value directly') }}</span
              >
            </label>
          </div>
          <div class="form-check mt-1">
            <input
              id="sign-inv"
              v-model="signHandling"
              type="radio"
              value="inverted"
              class="form-check-input"
            />
            <label for="sign-inv" class="form-check-label">
              {{ __('Inverted') }}
              <span class="text-muted"
                >—
                {{
                  __(
                    'negate the parsed value (bank exports debits as positive)',
                  )
                }}</span
              >
            </label>
          </div>
        </div>
      </div>

      <!-- Live preview -->
      <div class="mb-2 mt-4 fw-bold">
        {{
          __('Preview with current settings (first :count data rows):', {
            count: previewRowCount,
          })
        }}
      </div>
      <CsvPreviewTable :headers="displayHeaders" :data-rows="previewRows" />
    </div>

    <!-- ────────────────────────────────────────────────── -->
    <!-- Step 3: Column mapping                            -->
    <!-- ────────────────────────────────────────────────── -->
    <div v-if="currentStep === 3">
      <!-- Mapping requirements status panel (always visible) -->
      <div
        class="d-flex flex-wrap gap-3 mb-3 p-2 bg-body-secondary border rounded"
      >
        <span :class="hasDateMapped ? 'text-success' : 'text-body-secondary'">
          <i
            :class="hasDateMapped ? 'fa fa-circle-check' : 'fa fa-circle'"
            class="me-1"
          ></i>
          <code>date</code>
          <span class="text-body-tertiary ms-1">({{ __('required') }})</span>
        </span>
        <span
          v-if="hasDateMapped"
          :class="dateFormat ? 'text-success' : 'text-warning'"
        >
          <i
            :class="
              dateFormat ? 'fa fa-circle-check' : 'fa fa-exclamation-circle'
            "
            class="me-1"
          ></i>
          {{ __('date format') }}
          <span class="text-body-tertiary ms-1">({{ __('required') }})</span>
        </span>
        <span :class="hasAmountMapped ? 'text-success' : 'text-body-secondary'">
          <i
            :class="hasAmountMapped ? 'fa fa-circle-check' : 'fa fa-circle'"
            class="me-1"
          ></i>
          <code>amount</code>
          <span class="text-body-tertiary ms-1">({{ __('required') }})</span>
        </span>
        <span :class="hasPayeeMapped ? 'text-success' : 'text-body-secondary'">
          <i
            :class="hasPayeeMapped ? 'fa fa-circle-check' : 'fa fa-circle-dot'"
            class="me-1"
          ></i>
          <code>payee</code>
          <span class="text-body-tertiary ms-1">({{ __('recommended') }})</span>
        </span>
      </div>

      <!-- Duplicate mapping warning (only for actual problems) -->
      <div
        v-if="mappingValidationError"
        class="alert alert-warning small py-2 mb-3"
      >
        <i class="fa fa-exclamation-triangle me-1"></i>
        {{ mappingValidationError }}
      </div>

      <!-- Date format panel - shown above the table once a date column is selected -->
      <div
        v-if="dateMappedIndex >= 0"
        class="mb-3 p-3 bg-body-secondary border rounded"
      >
        <div class="small fw-semibold mb-2">
          <i class="fa fa-calendar me-1"></i>{{ __('Date format') }}
          <span class="fw-normal text-muted ms-1">
            ({{ __('column:') }} {{ displayHeaders[dateMappedIndex] }})
          </span>
        </div>
        <DateFormatSelector
          :sample-values="columnSamples[dateMappedIndex] || []"
          :model-value="dateFormat"
          :bordered="false"
          :show-samples="false"
          @update:model-value="updateDateFormat($event)"
        />
      </div>

      <!-- Integrated mapping + preview table -->
      <div class="table-responsive border rounded">
        <table class="table table-sm table-bordered mb-0 small">
          <thead>
            <!-- Row 1: source column header names -->
            <tr class="table-active">
              <th
                v-for="(h, i) in displayHeaders"
                :key="'h-' + i"
                class="fw-normal text-muted text-nowrap"
                style="
                  max-width: 180px;
                  overflow: hidden;
                  text-overflow: ellipsis;
                "
                :title="h"
              >
                {{ h }}
              </th>
            </tr>
            <!-- Row 2: canonical field mapping dropdowns -->
            <tr>
              <th
                v-for="(col, i) in columnMappings"
                :key="'m-' + i"
                class="p-1 align-top"
                style="min-width: 140px"
              >
                <select
                  :value="col.canonical"
                  class="form-select form-select-sm"
                  :class="col.canonical !== 'ignore' ? 'border-success' : ''"
                  @change="updateMapping(i, $event.target.value)"
                >
                  <option value="ignore">{{ __('— Ignore —') }}</option>
                  <option value="date">{{ __('date') }}</option>
                  <option value="amount">{{ __('amount') }}</option>
                  <option value="payee">{{ __('payee') }}</option>
                  <option value="comment">{{ __('comment') }}</option>
                  <option value="reference">{{ __('reference') }}</option>
                  <option value="category">{{ __('category') }}</option>
                </select>

                <div
                  v-if="duplicateWarningForColumn(i)"
                  class="small text-warning mt-1"
                >
                  <i class="fa fa-exclamation-circle me-1"></i
                  >{{ duplicateWarningForColumn(i) }}
                </div>
                <div
                  v-if="confidenceNoteForHeader(col.header)"
                  class="small text-info mt-1"
                >
                  <i class="fa fa-info-circle me-1"></i
                  >{{ confidenceNoteForHeader(col.header) }}
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="previewRows.length === 0">
              <td
                :colspan="columnMappings.length || 1"
                class="text-muted text-center py-2"
              >
                {{ __('No data rows to preview') }}
              </td>
            </tr>
            <tr v-for="(row, ri) in previewRows" :key="ri">
              <td
                v-for="(col, ci) in columnMappings"
                :key="ci"
                class="text-nowrap"
                :class="col.canonical === 'ignore' ? 'text-muted' : ''"
                style="
                  max-width: 200px;
                  overflow: hidden;
                  text-overflow: ellipsis;
                "
                :title="row[ci] ?? ''"
              >
                {{ (row[ci] ?? '') !== '' ? row[ci] : '—' }}
                <!-- Parsed value preview: amount -->
                <div
                  v-if="col.canonical === 'amount' && (row[ci] ?? '') !== ''"
                  class="font-monospace"
                  :class="
                    parseAmountPreview(row[ci]) !== null
                      ? 'text-success'
                      : 'text-danger'
                  "
                >
                  →
                  {{
                    parseAmountPreview(row[ci]) !== null
                      ? parseAmountPreview(row[ci])
                      : __('?')
                  }}
                </div>
                <!-- Parsed value preview: date -->
                <div
                  v-if="col.canonical === 'date' && (row[ci] ?? '') !== ''"
                  class="font-monospace"
                  :class="
                    parseDatePreview(row[ci]) !== null
                      ? 'text-success'
                      : 'text-warning'
                  "
                >
                  →
                  {{
                    parseDatePreview(row[ci]) !== null
                      ? parseDatePreview(row[ci])
                      : __('set format ↑')
                  }}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ────────────────────────────────────────────────── -->
    <!-- Step 4: Review and save                           -->
    <!-- ────────────────────────────────────────────────── -->
    <div v-if="currentStep === 4">
      <div class="mb-3">
        <div class="fw-bold mb-2">{{ __('Profile summary') }}</div>
        <dl class="row mb-0">
          <dt class="col-5 col-md-3 text-muted">{{ __('Name') }}</dt>
          <dd class="col-7 col-md-9">{{ profileName }}</dd>
          <dt class="col-5 col-md-3 text-muted">{{ __('Delimiter') }}</dt>
          <dd class="col-7 col-md-9 font-monospace">
            {{ delimiterLabel(effectiveDelimiter) }}
          </dd>
          <dt class="col-5 col-md-3 text-muted">{{ __('Header row') }}</dt>
          <dd class="col-7 col-md-9">
            {{ hasHeaderRow ? __('Yes') : __('No') }}
          </dd>
          <dt class="col-5 col-md-3 text-muted">{{ __('Date format') }}</dt>
          <dd class="col-7 col-md-9 font-monospace">
            {{ primaryDateFormat || __('Not set') }}
          </dd>
          <dt class="col-5 col-md-3 text-muted">
            {{ __('Decimal separator') }}
          </dt>
          <dd class="col-7 col-md-9 font-monospace">{{ decimalSeparator }}</dd>
          <dt class="col-5 col-md-3 text-muted">
            {{ __('Thousand separator') }}
          </dt>
          <dd class="col-7 col-md-9 font-monospace">
            {{ thousandSeparator || __('None') }}
          </dd>
          <dt class="col-5 col-md-3 text-muted">{{ __('Sign handling') }}</dt>
          <dd class="col-7 col-md-9">{{ signHandling }}</dd>
          <dt class="col-12 text-muted mb-1">{{ __('Column mappings') }}</dt>
          <dd class="col-12 mb-0">
            <div class="d-flex flex-wrap gap-1">
              <span
                v-for="col in columnMappings.filter(
                  (c) => c.canonical !== 'ignore',
                )"
                :key="col.header"
                class="badge bg-body-secondary text-body border font-monospace"
                style="font-size: 0.8em"
              >
                {{ col.header }} → {{ col.canonical }}
              </span>
              <span
                v-if="
                  columnMappings.filter((c) => c.canonical === 'ignore').length
                "
                class="badge bg-body-secondary text-body-secondary border"
                style="font-size: 0.8em"
              >
                +
                {{
                  columnMappings.filter((c) => c.canonical === 'ignore').length
                }}
                {{ __('ignored') }}
              </span>
            </div>
          </dd>
        </dl>
      </div>

      <!-- Save errors from API -->
      <div v-if="saveErrors" class="alert alert-danger small mb-3">
        <div v-if="typeof saveErrors === 'string'">{{ saveErrors }}</div>
        <ul v-else class="mb-0 ps-3">
          <li v-for="(msgs, field) in saveErrors" :key="field">
            <strong>{{ field }}:</strong>
            {{ Array.isArray(msgs) ? msgs.join(' ') : msgs }}
          </li>
        </ul>
      </div>
    </div>

    <!-- Navigation buttons -->
    <div class="d-flex gap-2 mt-4 border-top pt-3">
      <button
        v-if="currentStep > 1"
        type="button"
        class="btn btn-sm btn-outline-secondary"
        :disabled="saving"
        @click="prevStep"
      >
        <i class="fa fa-arrow-left me-1"></i>{{ __('Back') }}
      </button>

      <button
        v-if="currentStep < 4"
        type="button"
        class="btn btn-sm btn-primary"
        :disabled="!canAdvance"
        @click="nextStep"
      >
        {{ __('Next') }}<i class="fa fa-arrow-right ms-1"></i>
      </button>

      <button
        v-if="currentStep === 4"
        type="button"
        class="btn btn-sm btn-primary"
        :disabled="saving"
        @click="save"
      >
        <span
          v-if="saving"
          class="spinner-border spinner-border-sm me-1"
        ></span>
        {{ __('Save profile') }}
      </button>

      <button
        type="button"
        class="btn btn-sm btn-outline-secondary ms-auto"
        :disabled="saving"
        @click="$emit('cancel')"
      >
        {{ __('Cancel') }}
      </button>
    </div>
  </div>
</template>

<script>
  import axios from 'axios';
  import { __ } from '@/shared/lib/i18n';
  import { tryParseDate, DATE_PATTERNS } from '../utils/dateFormatUtils.js';
  import CsvPreviewTable from './CsvPreviewTable.vue';
  import DateFormatSelector from './DateFormatSelector.vue';

  // ─── CSV utilities ────────────────────────────────────────────────────────

  const DELIMITER_CANDIDATES = [',', ';', '\t', '|'];

  /**
   * Parse CSV text into records, correctly handling quoted fields that contain
   * embedded newlines. Returns an array of records (each record is a string[]).
   * Empty records (all-empty fields) are skipped.
   */
  function parseCsvRecords(text, delimiter) {
    const records = [];
    let field = '';
    let inQuotes = false;
    let currentRecord = [];
    const len = text.length;

    for (let i = 0; i < len; i++) {
      const c = text[i];

      if (inQuotes) {
        if (c === '"') {
          if (i + 1 < len && text[i + 1] === '"') {
            field += '"';
            i++;
          } else {
            inQuotes = false;
          }
        } else if (c === '\r') {
          // Normalize CRLF → LF inside quoted fields; skip bare CR.
          if (i + 1 < len && text[i + 1] === '\n') {
            field += '\n';
            i++;
          }
        } else {
          field += c;
        }
      } else if (c === '"') {
        inQuotes = true;
      } else if (c === delimiter) {
        currentRecord.push(field.trim());
        field = '';
      } else if (c === '\n' || c === '\r') {
        if (c === '\r' && i + 1 < len && text[i + 1] === '\n') i++;
        currentRecord.push(field.trim());
        field = '';
        if (currentRecord.some((f) => f !== '')) records.push(currentRecord);
        currentRecord = [];
      } else {
        field += c;
      }
    }

    // Flush any trailing content not terminated by a newline.
    currentRecord.push(field.trim());
    if (currentRecord.some((f) => f !== '')) records.push(currentRecord);

    return records;
  }

  /** Score each candidate delimiter and return the winner. */
  function detectDelimiter(lines) {
    const scores = {};
    for (const d of DELIMITER_CANDIDATES) {
      const escaped = d === '|' ? '\\|' : d === '\t' ? '\t' : d;
      const re = new RegExp(escaped, 'g');
      const counts = lines.map((l) => (l.match(re) || []).length);
      const maxCount = Math.max(...counts);
      if (maxCount === 0) {
        scores[d] = 0;
        continue;
      }
      const consistency =
        counts.filter((c) => c === maxCount).length / counts.length;
      scores[d] = maxCount * consistency;
    }
    let best = ',';
    let bestScore = -1;
    for (const [d, s] of Object.entries(scores)) {
      if (s > bestScore) {
        bestScore = s;
        best = d;
      }
    }
    return best;
  }

  /** Heuristic: first row with no numeric values while subsequent rows have them → header. */
  function detectHasHeader(parsedLines) {
    if (parsedLines.length < 2) return false;
    const firstRow = parsedLines[0];
    const rest = parsedLines.slice(1);
    const firstHasNoNumbers = firstRow.every((c) =>
      isNaN(parseFloat(c.replace(/[\s,.]/g, ''))),
    );
    const restHaveNumbers = rest.some((row) =>
      row.some((c) => !isNaN(parseFloat(c.replace(/[\s,.]/g, '')))),
    );
    return firstHasNoNumbers && restHaveNumbers;
  }

  /**
   * Detect decimal separator from CSV data rows.
   * Looks at all cell values and counts how many look like numbers with a comma
   * vs. a dot as their decimal mark. Returns ',' or '.' (or null if inconclusive).
   */
  function detectDecimalSeparator(dataRows) {
    let commaDecimalVotes = 0;
    let dotDecimalVotes = 0;

    for (const row of dataRows) {
      for (const cell of row) {
        const stripped = cell
          .trim()
          .replace(/[A-Z]{3}$/, '')
          .trim();
        // Pattern: digit, comma, exactly 2 digits at end → comma is decimal
        if (/\d,\d{2}$/.test(stripped)) {
          commaDecimalVotes++;
        }
        // Pattern: digit, dot, exactly 2 digits at end → dot is decimal
        if (/\d\.\d{2}$/.test(stripped)) {
          dotDecimalVotes++;
        }
      }
    }

    if (commaDecimalVotes === 0 && dotDecimalVotes === 0) return null;
    return commaDecimalVotes > dotDecimalVotes ? ',' : '.';
  }

  /** Human-readable label for a delimiter character. */
  function delimiterLabel(d) {
    if (d === '\t') return '\\t (tab)';
    if (d === ',') return ', (comma)';
    if (d === ';') return '; (semicolon)';
    if (d === '|') return '| (pipe)';
    return d;
  }

  // ─── Component ────────────────────────────────────────────────────────────

  export default {
    name: 'ProfileCreationWizard',
    components: { CsvPreviewTable, DateFormatSelector },

    props: {
      /** Optional account ID passed to AI suggestion for contextual hints. */
      accountId: {
        type: [Number, String],
        default: null,
      },
      /** Whether the authenticated user has an AiProviderConfig. */
      hasAiProvider: {
        type: Boolean,
        default: false,
      },
      /** Number of data rows shown in preview tables throughout the wizard. */
      previewRowCount: {
        type: Number,
        default: 10,
      },
      /** When editing: the ID of the profile to update (triggers PATCH instead of POST). */
      editProfileId: {
        type: Number,
        default: null,
      },
      /** When editing: the existing profile data to pre-populate the wizard. */
      initialProfile: {
        type: Object,
        default: null,
      },
    },

    emits: ['saved', 'cancel'],

    data() {
      return {
        currentStep: 1,

        // ── File / raw data
        sampleFile: null,
        rawText: '', // raw file text (first ~20 non-empty lines worth)

        // ── Auto-detected values
        detectedDelimiter: ',',
        detectedHasHeader: true,

        // ── Step-2 settings (initialised from detected values)
        delimiterChoice: ',', // preset or '__custom__'
        customDelimiter: '',
        hasHeaderRow: true,
        decimalSeparator: '.',
        thousandSeparator: '',
        signHandling: 'as_is',
        profileName: '',

        // ── Parsed state (recomputed when settings change)
        parsedAllRows: [], // all rows from raw lines using current delimiter
        headers: [], // column headers
        dataRows: [], // data rows (without header)

        // ── Column mapping (Step 3)
        columnMappings: [], // [{header, canonical}]
        dateFormat: '', // PHP date format string (single value, matches FileImportProfile model)

        // ── AI confidence notes keyed by source header
        confidenceNotes: {}, // { 'HeaderName': 'note text' }

        // ── Validation
        mappingValidationError: null,
        validationErrors: {},

        // ── Save
        saving: false,
        saveErrors: null,
        generalError: null,

        // ── AI suggestion
        showAiPanel: false,
        aiSuggesting: false,
        aiError: null,
      };
    },

    created() {
      if (this.initialProfile && this.editProfileId) {
        this.initFromProfile(this.initialProfile);
        this.currentStep = 2;
      }
    },

    computed: {
      isEditMode() {
        return !!this.editProfileId;
      },

      stepLabels() {
        return [
          __('File selection'),
          __('Parser settings'),
          __('Column mapping'),
          __('Review & save'),
        ];
      },

      stepLabel() {
        return this.stepLabels[this.currentStep - 1] || '';
      },

      effectiveDelimiter() {
        return this.delimiterChoice === '__custom__'
          ? this.customDelimiter || ','
          : this.delimiterChoice;
      },

      /** Headers shown in the preview table (auto-generated indices when no header row). */
      displayHeaders() {
        if (this.hasHeaderRow) return this.headers;
        return this.headers.map((_, i) => `#${i + 1}`);
      },

      /** First `previewRowCount` data rows for preview tables. */
      previewRows() {
        return this.dataRows.slice(0, this.previewRowCount);
      },

      /** Sample values per column index (up to `previewRowCount` from data rows). */
      columnSamples() {
        return this.headers.map((_, colIdx) =>
          this.dataRows
            .slice(0, this.previewRowCount)
            .map((row) => row[colIdx] ?? '')
            .filter((v) => v !== ''),
        );
      },

      /** Index of the first column mapped to 'date', or -1 (used for table preview). */
      dateMappedIndex() {
        return this.columnMappings.findIndex((c) => c.canonical === 'date');
      },

      hasDateMapped() {
        return this.columnMappings.some((c) => c.canonical === 'date');
      },

      hasAmountMapped() {
        return this.columnMappings.some((c) => c.canonical === 'amount');
      },

      hasPayeeMapped() {
        return this.columnMappings.some((c) => c.canonical === 'payee');
      },

      hasDuplicateMappings() {
        return this.columnMappings.some(
          (_, i) => !!this.duplicateWarningForColumn(i),
        );
      },

      /** PHP date format — single value matching the FileImportProfile model field. */
      primaryDateFormat() {
        return this.dateFormat;
      },

      /**
       * Sample values from the first column that looks like it contains dates.
       * Used to drive the DateFormatSelector in Step 2 before mapping is done.
       */
      autoDetectedDateSamples() {
        for (let i = 0; i < this.headers.length; i++) {
          const samples = this.columnSamples[i] || [];
          const looksLikeDates = samples.some((v) =>
            DATE_PATTERNS.some((p) => v && p.regex.test(String(v).trim())),
          );
          if (looksLikeDates) return samples;
        }
        return [];
      },

      canAdvance() {
        if (this.currentStep === 1) {
          if (this.isEditMode && this.headers.length > 0) {
            return !this.aiSuggesting;
          }
          return (
            this.sampleFile !== null &&
            this.headers.length > 0 &&
            !this.aiSuggesting
          );
        }
        if (this.currentStep === 2) {
          return this.profileName.trim().length > 0 && this.headers.length > 0;
        }
        if (this.currentStep === 3) {
          return (
            this.hasDateMapped &&
            this.hasAmountMapped &&
            !!this.dateFormat &&
            !this.hasDuplicateMappings
          );
        }
        return false;
      },
    },

    methods: {
      __,
      delimiterLabel,

      // ── Stepper helpers ──────────────────────────────────────────────────

      stepCircleClass(n) {
        if (n < this.currentStep) return 'wizard-circle-done';
        if (n === this.currentStep) return 'wizard-circle-active';
        return 'wizard-circle-pending';
      },

      stepLabelClass(n) {
        if (n === this.currentStep) return 'fw-semibold text-primary';
        if (n < this.currentStep) return 'text-success';
        return 'text-muted';
      },

      // ── Edit mode initialisation ─────────────────────────────────────────

      initFromProfile(profile) {
        this.profileName = profile.name || '';
        const delimiter = profile.delimiter || ',';
        this.delimiterChoice = DELIMITER_CANDIDATES.includes(delimiter)
          ? delimiter
          : '__custom__';
        if (!DELIMITER_CANDIDATES.includes(delimiter)) {
          this.customDelimiter = delimiter;
        }
        this.hasHeaderRow = profile.has_header_row !== false;
        this.decimalSeparator = profile.decimal_separator || '.';
        this.thousandSeparator =
          profile.thousand_separator !== null &&
          profile.thousand_separator !== undefined
            ? profile.thousand_separator
            : '';
        this.signHandling = profile.sign_handling || 'as_is';
        this.dateFormat = profile.date_format || '';

        if (profile.mapping_json && typeof profile.mapping_json === 'object') {
          const entries = Object.entries(profile.mapping_json);
          this.headers = entries.map(([header]) => header);
          this.columnMappings = entries.map(([header, canonical]) => ({
            header,
            canonical: canonical || 'ignore',
          }));
        }
      },

      // ── File handling ──────────────────────────────────────────────────────

      async onFileChange(event) {
        const file = event.target.files?.[0];
        if (!file) return;
        this.sampleFile = file;
        await this.processFile(file);
        if (!this.profileName) {
          this.profileName = file.name.replace(/\.[^.]+$/, '');
        }
      },

      async processFile(file) {
        let text;
        try {
          text = await this.readFileAsText(file, 20);
        } catch (_err) {
          this.generalError = __(
            'The file could not be read. Please try a different file.',
          );
          return;
        }
        this.rawText = text;

        // Auto-detect (use naive line split only for the delimiter heuristic)
        const linesForDetection = text
          .split(/\r?\n/)
          .filter((l) => l.trim().length > 0);
        const delim = detectDelimiter(linesForDetection);
        this.detectedDelimiter = delim;

        const allParsed = parseCsvRecords(text, delim);
        this.detectedHasHeader = detectHasHeader(allParsed);

        // Apply detected values to settings (only on first file load)
        this.delimiterChoice = DELIMITER_CANDIDATES.includes(delim)
          ? delim
          : '__custom__';
        if (!DELIMITER_CANDIDATES.includes(delim)) this.customDelimiter = delim;
        this.hasHeaderRow = this.detectedHasHeader;

        // Auto-detect decimal separator from data rows (skip header if present)
        const dataRows = this.detectedHasHeader
          ? allParsed.slice(1)
          : allParsed;
        const detectedDecimal = detectDecimalSeparator(dataRows);
        if (detectedDecimal) {
          this.decimalSeparator = detectedDecimal;
        }

        this.rebuildParsedState(text, delim, this.hasHeaderRow);
      },

      readFileAsText(file, maxLines) {
        return new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onerror = () => reject(new Error('File could not be read.'));
          reader.onload = (e) => {
            try {
              const buffer = e.target.result;
              const uint8 = new Uint8Array(buffer);

              // Detect encoding from BOM; fall back to replacement-character heuristic.
              let encoding = 'utf-8';
              if (
                uint8.length >= 3 &&
                uint8[0] === 0xef &&
                uint8[1] === 0xbb &&
                uint8[2] === 0xbf
              ) {
                encoding = 'utf-8';
              } else if (
                uint8.length >= 2 &&
                uint8[0] === 0xff &&
                uint8[1] === 0xfe
              ) {
                encoding = 'utf-16le';
              } else if (
                uint8.length >= 2 &&
                uint8[0] === 0xfe &&
                uint8[1] === 0xff
              ) {
                encoding = 'utf-16be';
              } else {
                // Probe the first 4 KB; if UTF-8 produces replacement chars, use windows-1252.
                const probe = new TextDecoder('utf-8', { fatal: false }).decode(
                  buffer.slice(0, 4096),
                );
                if (probe.includes('�')) {
                  encoding = 'windows-1252';
                }
              }

              const text = new TextDecoder(encoding, { fatal: false }).decode(
                buffer,
              );

              // Trim to first maxLines non-empty lines for efficiency.
              const lines = text.split(/\r?\n/);
              let count = 0;
              let cut = lines.length;
              for (let i = 0; i < lines.length; i++) {
                if (lines[i].trim()) count++;
                if (count >= maxLines) {
                  cut = i + 1;
                  break;
                }
              }
              resolve(lines.slice(0, cut).join('\n'));
            } catch (err) {
              reject(err);
            }
          };
          reader.readAsArrayBuffer(file);
        });
      },

      rebuildParsedState(rawText, delimiter, hasHeader) {
        if (!rawText) {
          // No file loaded yet (e.g. edit mode before a new file is selected) —
          // preserve existing state rather than wiping headers/mappings.
          return;
        }

        const parsed = parseCsvRecords(rawText, delimiter);
        if (parsed.length === 0) {
          this.headers = [];
          this.dataRows = [];
          this.parsedAllRows = [];
          return;
        }
        this.parsedAllRows = parsed;

        const colCount = Math.max(...parsed.map((r) => r.length));

        if (hasHeader && parsed.length > 0) {
          const headerRow = parsed[0];
          // Ensure header row has an entry for every column
          this.headers = Array.from(
            { length: colCount },
            (_, i) => headerRow[i] ?? `#${i + 1}`,
          );
          this.dataRows = parsed.slice(1);
        } else {
          this.headers = Array.from(
            { length: colCount },
            (_, i) => `#${i + 1}`,
          );
          this.dataRows = parsed;
        }

        // Initialise column mappings (preserve any existing that match headers)
        const existingMap = Object.fromEntries(
          this.columnMappings.map((c) => [c.header, c.canonical]),
        );
        this.columnMappings = this.headers.map((h) => ({
          header: h,
          canonical: existingMap[h] ?? 'ignore',
        }));
      },

      onSettingsChange() {
        this.rebuildParsedState(
          this.rawText,
          this.effectiveDelimiter,
          this.hasHeaderRow,
        );
      },

      // ── Step navigation ────────────────────────────────────────────────────

      nextStep() {
        if (this.currentStep === 3) {
          this.validateMappings();
          if (this.mappingValidationError) return;
        }
        if (this.currentStep === 2) {
          const trimmed = this.profileName.trim();
          if (!trimmed) {
            this.validationErrors = {
              profileName: __('Profile name is required.'),
            };
            return;
          }
        }
        this.validationErrors = {};
        this.currentStep = Math.min(4, this.currentStep + 1);
        this.saveErrors = null;
        this.generalError = null;
      },

      prevStep() {
        this.currentStep = Math.max(1, this.currentStep - 1);
        this.saveErrors = null;
        this.generalError = null;
      },

      // ── Column mapping helpers ────────────────────────────────────────────

      updateMapping(index, canonical) {
        this.columnMappings = this.columnMappings.map((c, i) =>
          i === index ? { ...c, canonical } : c,
        );
        this.validateMappings();
      },

      updateDateFormat(format) {
        this.dateFormat = format;
      },

      validateMappings() {
        if (this.hasDuplicateMappings) {
          this.mappingValidationError = __(
            'Duplicate column mappings must be resolved before continuing.',
          );
          return;
        }
        this.mappingValidationError = null;
      },

      duplicateWarningForColumn(index) {
        const canonical = this.columnMappings[index]?.canonical;
        if (!canonical || canonical === 'ignore') return '';
        // comment and reference allow multiple mappings
        if (canonical === 'comment' || canonical === 'reference') return '';
        const duplicates = this.columnMappings.filter(
          (c, i) => i !== index && c.canonical === canonical,
        );
        if (duplicates.length > 0) return __('Duplicate mapping');
        return '';
      },

      confidenceNoteForHeader(header) {
        return this.confidenceNotes[header] || '';
      },

      parseAmountPreview(raw) {
        if (!raw && raw !== 0) return null;
        let cleaned = String(raw).trim();
        // Strip trailing 3-letter uppercase currency code (e.g. "HUF", "EUR") — mirrors backend logic
        cleaned = cleaned.replace(/\s*[A-Z]{3}$/, '').trimEnd();
        if (this.thousandSeparator) {
          cleaned = cleaned.split(this.thousandSeparator).join('');
        }
        if (this.decimalSeparator && this.decimalSeparator !== '.') {
          cleaned = cleaned.replace(this.decimalSeparator, '.');
        }
        // Remove any remaining whitespace
        cleaned = cleaned.replace(/\s+/g, '');
        // Strict validation: reject if anything other than a signed decimal number remains
        if (!/^-?\d+(\.\d+)?$/.test(cleaned)) {
          return null;
        }
        return parseFloat(cleaned);
      },

      parseDatePreview(raw) {
        return tryParseDate(raw, this.dateFormat);
      },

      // ── Save ─────────────────────────────────────────────────────────────

      async save() {
        this.validateMappings();
        if (this.mappingValidationError) {
          this.currentStep = 3;
          return;
        }

        const mappingJson = {};
        this.columnMappings.forEach((col, i) => {
          if (col.canonical && col.canonical !== 'ignore') {
            mappingJson[col.header] = col.canonical;
          }
        });

        const payload = {
          name: this.profileName.trim(),
          delimiter: this.effectiveDelimiter,
          has_header_row: this.hasHeaderRow,
          date_format: this.primaryDateFormat || null,
          decimal_separator: this.decimalSeparator || null,
          thousand_separator: this.thousandSeparator || null,
          sign_handling: this.signHandling || null,
          mapping_json: mappingJson,
        };

        this.saving = true;
        this.saveErrors = null;
        this.generalError = null;

        try {
          const response = this.editProfileId
            ? await axios.patch(
                `/api/v1/imports/file-profiles/${this.editProfileId}`,
                payload,
              )
            : await axios.post('/api/v1/imports/file-profiles', payload);
          this.$emit('saved', response.data);
        } catch (err) {
          if (err?.response?.data?.errors) {
            this.saveErrors = err.response.data.errors;
          } else if (err?.response?.data?.error?.message) {
            this.generalError = err.response.data.error.message;
          } else {
            this.generalError = __(
              'Save failed due to a network or server error.',
            );
          }
        } finally {
          this.saving = false;
        }
      },

      // ── AI suggestion ────────────────────────────────────────────────────

      async requestAiSuggestion() {
        if (!this.sampleFile) return;

        this.aiSuggesting = true;
        this.aiError = null;

        try {
          const formData = new FormData();
          formData.append('file', this.sampleFile);
          if (this.accountId) {
            formData.append('account_id', this.accountId);
          }

          const response = await axios.post(
            '/api/v1/imports/file-profiles/suggest',
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
          );

          this.applyAiSuggestion(response.data.data);
          this.showAiPanel = false;

          // Advance to step 2 so the user can review the pre-filled settings.
          if (this.headers.length > 0) {
            this.currentStep = 2;
          }
        } catch (err) {
          if (err?.response?.status === 422) {
            const errors = err.response.data?.errors;
            if (errors) {
              const firstKey = Object.keys(errors)[0];
              this.aiError = errors[firstKey]?.[0] || __('Invalid request.');
            } else {
              this.aiError =
                err.response.data?.error?.message ||
                err.response.data?.message ||
                __('Invalid request.');
            }
          } else {
            this.aiError =
              err?.response?.data?.error?.message ||
              err?.response?.data?.message ||
              __('AI provider request failed. Please try again.');
          }
        } finally {
          this.aiSuggesting = false;
        }
      },

      applyAiSuggestion(data) {
        // Parser settings
        if (data.delimiter) {
          this.delimiterChoice = DELIMITER_CANDIDATES.includes(data.delimiter)
            ? data.delimiter
            : '__custom__';
          if (!DELIMITER_CANDIDATES.includes(data.delimiter)) {
            this.customDelimiter = data.delimiter;
          }
        }
        if (typeof data.has_header_row === 'boolean') {
          this.hasHeaderRow = data.has_header_row;
        }
        if (data.decimal_separator) {
          this.decimalSeparator = data.decimal_separator;
        }
        if (data.thousand_separator !== undefined) {
          this.thousandSeparator = data.thousand_separator;
        }
        if (data.sign_handling) {
          this.signHandling = data.sign_handling;
        }

        // Rebuild parsed state with new delimiter/header settings
        if (this.rawText) {
          this.rebuildParsedState(
            this.rawText,
            this.effectiveDelimiter,
            this.hasHeaderRow,
          );
        }

        // Apply date format (single global setting matching the model)
        if (data.date_format) {
          this.dateFormat = data.date_format;
        }

        // Apply column mappings from AI response
        if (data.mapping_json && typeof data.mapping_json === 'object') {
          this.columnMappings = this.columnMappings.map((col) => ({
            ...col,
            canonical: data.mapping_json[col.header] ?? col.canonical,
          }));
        }

        // Store confidence notes keyed by header name
        this.confidenceNotes = {};
        if (Array.isArray(data.confidence_notes)) {
          data.confidence_notes.forEach((note) => {
            if (note.field && note.note) {
              this.confidenceNotes[note.field] = note.note;
            }
          });
        }

        this.validateMappings();
      },
    },
  };
</script>

<style scoped>
  /* ── Wizard stepper ──────────────────────────────────────── */
  .wizard-step-item {
    flex-shrink: 0;
    min-width: 4.5rem;
    max-width: 7rem;
  }

  .wizard-step-circle {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    transition: background-color 0.2s;
  }

  .wizard-circle-active {
    background-color: var(--cui-primary, #0d6efd);
    color: #fff;
  }

  .wizard-circle-done {
    background-color: var(--cui-success, #198754);
    color: #fff;
  }

  .wizard-circle-pending {
    background-color: var(--cui-secondary-bg, #e9ecef);
    color: var(--cui-secondary-color, #6c757d);
    border: 2px solid var(--cui-border-color, #dee2e6);
  }

  .wizard-step-label {
    margin-top: 0.35rem;
    line-height: 1.2;
  }

  .wizard-step-connector {
    flex: 1;
    height: 2px;
    margin: 1.1rem 0.35rem 0;
    border-radius: 1px;
  }

  .connector-done {
    background-color: var(--cui-success, #198754);
  }

  .connector-pending {
    background-color: var(--cui-border-color, #dee2e6);
  }
</style>
