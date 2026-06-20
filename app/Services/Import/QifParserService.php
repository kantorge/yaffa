<?php

namespace App\Services\Import;

use App\Models\FileImportProfile;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class QifParserService
{
    /**
     * Default QIF marker → semantic field mapping.
     *
     * @var array<string, string>
     */
    private array $fieldMap = [
        'payee' => 'P',
        'comment' => 'M',
        'category' => 'L',
        'reference' => 'N',
    ];

    private string $amountSign = 'normal';

    public function applyProfile(FileImportProfile $profile): void
    {
        $options = is_array($profile->options_json) ? $profile->options_json : [];

        if (isset($options['field_map']) && is_array($options['field_map'])) {
            foreach ($options['field_map'] as $canonical => $marker) {
                if (isset($this->fieldMap[$canonical]) && is_string($marker) && $marker !== '') {
                    $this->fieldMap[$canonical] = mb_strtoupper($marker);
                }
            }
        }

        if (isset($options['amount_sign']) && $options['amount_sign'] === 'inverted') {
            $this->amountSign = 'inverted';
        }
    }

    /**
     * @return array{entries: list<array<string, mixed>>, warnings: list<string>}
     */
    public function parseFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! is_string($path)) {
            throw new RuntimeException('Unable to read uploaded QIF file.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Unable to read uploaded QIF file.');
        }

        [$normalizedContent, $encodingWarning] = $this->normalizeContentToUtf8($content);

        $parsed = $this->parseContent($normalizedContent);

        if ($encodingWarning !== null) {
            $parsed['warnings'][] = $encodingWarning;
            $parsed['warnings'] = array_values(array_unique($parsed['warnings']));
        }

        return $parsed;
    }

    /**
     * @return array{entries: list<array<string, mixed>>, warnings: list<string>}
     */
    public function parseContent(string $content): array
    {
        $entries = [];
        $warnings = [];
        $supportedTypes = ['Bank', 'Cash', 'CCard'];
        $activeType = null;
        $skipSection = false;
        $inAccountBlock = false;
        $currentEntry = null;

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        foreach ($lines as $lineNumber => $rawLine) {
            $line = mb_trim($rawLine);

            if ($line === '') {
                continue;
            }

            if ($line === '!Account') {
                $inAccountBlock = true;
                continue;
            }

            if ($inAccountBlock) {
                if ($line === '^') {
                    $inAccountBlock = false;
                }

                continue;
            }

            if (str_starts_with($line, '!Type:')) {
                $activeType = mb_trim(mb_substr($line, mb_strlen('!Type:')));
                $skipSection = ! in_array($activeType, $supportedTypes, true);

                if ($skipSection) {
                    $warnings[] = sprintf(
                        'Unsupported QIF section type "%s" at line %d was skipped.',
                        $activeType,
                        $lineNumber + 1,
                    );
                }

                continue;
            }

            if ($skipSection || $activeType === null) {
                continue;
            }

            if ($line === '^') {
                if ($currentEntry !== null) {
                    $entries[] = $this->finalizeEntry($currentEntry);
                    $currentEntry = null;
                }

                continue;
            }

            if ($currentEntry === null) {
                $currentEntry = [
                    'date_raw' => null,
                    'amount_raw' => null,
                    'payee' => null,
                    'memo' => null,
                    'category' => null,
                    'reference' => null,
                    'raw_lines' => [],
                    'warnings' => [],
                ];
            }

            $currentEntry['raw_lines'][] = $line;

            $marker = mb_strtoupper(mb_substr($line, 0, 1));
            $value = mb_trim(mb_substr($line, 1));

            switch ($marker) {
                case 'D':
                    $currentEntry['date_raw'] = $value;
                    break;
                case 'T':
                    $currentEntry['amount_raw'] = $value;
                    break;
                case 'S':
                case 'E':
                case '$':
                    $this->addUniqueWarning(
                        $currentEntry['warnings'],
                        'Split transaction detail was detected and kept in raw_entry, but split lines were not imported.',
                    );
                    break;
                default:
                    if ($marker === $this->fieldMap['payee']) {
                        $currentEntry['payee'] = $value;
                    } elseif ($marker === $this->fieldMap['comment']) {
                        $currentEntry['memo'] = $value;
                    } elseif ($marker === $this->fieldMap['category']) {
                        $currentEntry['category'] = $value;
                    } elseif ($marker === $this->fieldMap['reference']) {
                        $currentEntry['reference'] = $value;
                    } else {
                        $this->addUniqueWarning(
                            $currentEntry['warnings'],
                            sprintf('Unsupported QIF marker line "%s" was kept in raw_entry.', $line),
                        );
                    }
                    break;
            }
        }

        if ($currentEntry !== null) {
            $this->addUniqueWarning(
                $currentEntry['warnings'],
                'The last QIF entry was missing the terminator (^) and was imported at end of file.',
            );
            $entries[] = $this->finalizeEntry($currentEntry);
        }

        return [
            'entries' => $entries,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function finalizeEntry(array $entry): array
    {
        $amountRaw = $entry['amount_raw'];
        if ($this->amountSign === 'inverted' && is_string($amountRaw) && $amountRaw !== '') {
            $amountRaw = str_starts_with($amountRaw, '-')
                ? mb_substr($amountRaw, 1)
                : '-' . $amountRaw;
        }

        return [
            'date_raw' => $entry['date_raw'],
            'amount_raw' => $amountRaw,
            'payee' => $entry['payee'],
            'memo' => $entry['memo'],
            'category' => $entry['category'],
            'reference' => $entry['reference'],
            'raw_entry' => implode("\n", $entry['raw_lines']),
            'warnings' => array_values(array_unique($entry['warnings'])),
        ];
    }

    /**
     * @param  list<string>  $warnings
     */
    private function addUniqueWarning(array &$warnings, string $warning): void
    {
        if (! in_array($warning, $warnings, true)) {
            $warnings[] = $warning;
        }
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function normalizeContentToUtf8(string $content): array
    {
        if ($content === '' || mb_check_encoding($content, 'UTF-8')) {
            return [$content, null];
        }

        $supportedEncodings = ['UTF-8', 'Windows-1252', 'ISO-8859-2', 'ISO-8859-1'];
        $detectedEncoding = mb_detect_encoding($content, $supportedEncodings, true);

        if (is_string($detectedEncoding) && $detectedEncoding !== 'UTF-8') {
            $converted = mb_convert_encoding($content, 'UTF-8', $detectedEncoding);

            if (mb_check_encoding($converted, 'UTF-8')) {
                return [
                    $converted,
                    sprintf('Import file encoding (%s) was converted to UTF-8 before parsing.', $detectedEncoding),
                ];
            }
        }

        foreach (['Windows-1252', 'ISO-8859-2', 'ISO-8859-1'] as $fallbackEncoding) {
            $converted = mb_convert_encoding($content, 'UTF-8', $fallbackEncoding);

            if (mb_check_encoding($converted, 'UTF-8')) {
                return [
                    $converted,
                    sprintf('Import file encoding was converted to UTF-8 using fallback %s.', $fallbackEncoding),
                ];
            }
        }

        // Last-resort sanitization path keeps request successful while preventing JSON encoding errors.
        $sanitized = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

        return [
            $sanitized,
            'Import file contained invalid encoding bytes and was sanitized to UTF-8 before parsing.',
        ];
    }
}
