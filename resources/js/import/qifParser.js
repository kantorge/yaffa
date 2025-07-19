// Supported format tokens: YYYY, YY, MM, DD, with any separator
/**
 * Parses a date string according to the specified format and returns an ISO date string.
 *
 * Supported format tokens: YYYY, YY, MM, DD.
 * Example: parseDateWithFormat('2024-06-01', 'YYYY-MM-DD') returns '2024-06-01T00:00:00.000Z'
 *
 * @param {string} dateStr - The date string to parse.
 * @param {string} format - The format string representing the date structure.
 * @returns {string|null} The parsed date as an ISO string, or null if parsing fails.
 */
function parseDateWithFormat(dateStr, format) {
  const tokens = format.match(/(YYYY|YY|MM|DD)/g);
  const splitRegex = format.replace(/(YYYY|YY|MM|DD)/g, '(.+)');
  const match = new RegExp(`^${splitRegex}$`).exec(dateStr.trim());

  console.log(`Parsing date "${dateStr}" with format "${format}"`);
  console.log(`Tokens: ${tokens}, Match: ${match}`);
  console.log(match);

  if (!match || tokens.length !== match.length - 1) return null;

  let year, month, day;
  match.slice(1).forEach((value, i) => {
    const token = tokens[i];
    if (token === 'YYYY') year = value;
    if (token === 'YY') year = '20' + value;
    if (token === 'MM') month = value.padStart(2, '0');
    if (token === 'DD') day = value.padStart(2, '0');
  });

  const iso = `${year}-${month}-${day}T00:00:00`;
  const parsed = new Date(iso);
  return isNaN(parsed) ? null : parsed.toISOString();
}

export function detectDateFormat(dates) {
  // Validate input: must be non-empty array
  if (!Array.isArray(dates) || dates.length === 0) {
    return { format: null, error: 'empty_or_invalid_input' };
  }

  const separators = ['-', '/', '.', "'"];
  const formatsFound = new Map();

  // Helper: parse date parts into year, month, day per the pattern order
  function parseDateParts(dateStr, sep, order) {
    const parts = dateStr.split(sep);
    if (parts.length !== 3) return null;

    const p = parts.map((part) => parseInt(part, 10));
    if (p.some(isNaN)) return null;

    return {
      year: p[order[2]],
      month: p[order[0]],
      day: p[order[1]],
    };
  }

  // Check if a date is ambiguous: month and day both <= 12 and not equal
  function isAmbiguous(month, day) {
    return month <= 12 && day <= 12 && month !== day;
  }

  // Track ambiguous and valid (non-ambiguous) dates per format
  // Structure: { ambiguousCount, validCount, ambiguousDates, validDates }
  for (const sep of separators) {
    const patterns = [
      { format: `MM${sep}DD${sep}YYYY`, order: [0, 1, 2] },
      { format: `DD${sep}MM${sep}YYYY`, order: [1, 0, 2] },
      { format: `YYYY${sep}MM${sep}DD`, order: [0, 1, 2] },
      { format: `YYYY${sep}DD${sep}MM`, order: [0, 2, 1] },
    ];

    for (const { format, order } of patterns) {
      let ambiguousCount = 0;
      let validCount = 0;
      const ambiguousDates = [];
      const validDates = [];

      for (const dateStr of dates) {
        const parsed = parseDateParts(dateStr, sep, order);
        if (!parsed) continue; // invalid format

        const { year, month, day } = parsed;

        // Validate ranges
        if (month < 1 || month > 12 || day < 1 || day > 31 || year < 1000 || year > 9999) {
          continue;
        }

        if (isAmbiguous(month, day)) {
          ambiguousCount++;
          ambiguousDates.push(dateStr);
        } else {
          validCount++;
          validDates.push(dateStr);
        }
      }

      // Store counts and samples for decision logic
      if (!formatsFound.has(format)) {
        formatsFound.set(format, {
          ambiguousCount: 0,
          validCount: 0,
          ambiguousDates: [],
          validDates: [],
        });
      }
      const data = formatsFound.get(format);
      data.ambiguousCount += ambiguousCount;
      data.validCount += validCount;
      data.ambiguousDates.push(...ambiguousDates);
      data.validDates.push(...validDates);
    }
  }

  // Filter out formats without any valid (non-ambiguous) dates
  const filteredFormats = [...formatsFound.entries()]
    .filter(([, data]) => data.validCount > 0);

  if (filteredFormats.length === 0) {
    // All dates ambiguous or no matches at all
    return { format: null, error: 'ambiguous_or_no_match' };
  }

  // Collect formats that have valid dates
  const validFormats = filteredFormats.map(([format]) => format);

  // If multiple formats detected from non-ambiguous dates, error
  if (new Set(validFormats).size > 1) {
    return { format: null, error: 'multiple_conflicts' };
  }

  // Single non-ambiguous format candidate
  const chosenFormat = validFormats[0];

  // Check ambiguous dates consistency: ambiguous dates must fit chosen format
  const ambiguousDates = formatsFound.get(chosenFormat).ambiguousDates;

  // If ambiguous dates exist, verify that none fit any other format better
  if (ambiguousDates.length > 0) {
    // For each ambiguous date, ensure it doesn't fit another format better
    for (const ambiguousDate of ambiguousDates) {
      for (const [format, data] of formatsFound.entries()) {
        if (format === chosenFormat) continue;

        // If ambiguous date fits another format as valid, conflict => error
        if (data.validDates.includes(ambiguousDate)) {
          return { format: null, error: 'ambiguous_dates_conflict' };
        }
      }
    }
  }

  // All checks passed — return chosen format with no error
  return { format: chosenFormat, error: null };
}

function parseTransactions(lines, type, options) {
  const { dateFormat, strict = false } = options;
  if (!dateFormat) throw new Error(`"dateFormat" is required (e.g. "YYYY.MM.DD")`);

  const transactions = [];
  let currentTransaction = {};
  let currentDivision = {};

  for (let line of lines) {
    line = line.trim();
    if (!line) continue;

    if (line === '^') {
      if (Object.keys(currentTransaction).length) {
        transactions.push(currentTransaction);
        currentTransaction = {};
      }
      continue;
    }

    const code = line[0];
    const value = line.substring(1).trim();

    switch (code) {
      case 'D':
        currentTransaction.date = parseDateWithFormat(value, dateFormat);
        break;
      case 'T':
        currentTransaction.amount = parseFloat(value.replace(',', ''));
        break;
      case 'U':
        break;
      case 'N':
        currentTransaction.number = value;
        break;
      case 'M':
        currentTransaction.memo = value;
        break;
      case 'A':
        currentTransaction.address = currentTransaction.address || [];
        currentTransaction.address.push(value);
        break;
      case 'P':
        currentTransaction.payee = value.replace(/&amp;/g, '&');
        break;
      case 'L':
        const [cat, sub] = value.split(':');
        currentTransaction.category = cat;
        if (sub !== undefined) currentTransaction.subcategory = sub;
        break;
      case 'C':
        currentTransaction.clearedStatus = value;
        break;
      case 'S':
        const [splitCat, splitSub] = value.split(':');
        currentDivision.category = splitCat;
        if (splitSub !== undefined) currentDivision.subcategory = splitSub;
        break;
      case 'E':
        currentDivision.description = value;
        break;
      case '$':
        currentDivision.amount = parseFloat(value);
        currentTransaction.division = currentTransaction.division || [];
        currentTransaction.division.push(currentDivision);
        currentDivision = {};
        break;
      default:
        if (strict) throw new Error(`Unknown QIF field code: "${code}"`);
    }
  }

  if (Object.keys(currentTransaction).length) {
    transactions.push(currentTransaction);
  }

  return { type, transactions };
}

export function parseQIF(qifText, options = {}) {
  const lines = qifText.split(/\r?\n/);
  const blocks = [];
  let currentBlockLines = [];
  let currentType = null;

  for (let line of lines) {
    line = line.trim();
    if (!line) continue;

    if (line.startsWith('!Type:')) {
      if (currentType && currentBlockLines.length) {
        blocks.push(parseTransactions(currentBlockLines, currentType, options));
      }

      currentType = line.substring(6).trim();
      currentBlockLines = [];
    } else {
      currentBlockLines.push(line);
    }
  }

  if (currentType && currentBlockLines.length) {
    blocks.push(parseTransactions(currentBlockLines, currentType, options));
  }

  return blocks;
}
