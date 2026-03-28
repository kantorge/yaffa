<?php

namespace App\Services;

use App\Models\AiDocument;
use Exception;
use Illuminate\Support\Str;

class ProcessingHistoryRecorder
{
    public function appendProcessingHistory(
        AiDocument $document,
        string $step,
        string $prompt,
        string $response,
        bool $includeInPromptHistory = true,
    ): void {
        $history = $document->ai_chat_history;

        if (! is_array($history)) {
            $history = [];
        }

        $entry = [
            'timestamp' => now()->toIso8601String(),
            'step' => $step,
            'prompt' => $prompt,
            'response' => $response,
        ];

        if (! $includeInPromptHistory) {
            $entry['include_in_prompt_history'] = false;
        }

        $history[] = $entry;

        $document->ai_chat_history = $history;
        $document->saveQuietly();
    }

    public function appendLocalProcessingHistory(AiDocument $document, string $step, array $context, array $result): void
    {
        $this->appendProcessingHistory(
            $document,
            $step,
            $this->buildLocalHistoryPrompt($step, $context),
            $this->buildLocalHistoryResponse($result),
            false,
        );
    }

    public function appendAiFallbackHistoryAfterFailure(
        AiDocument $document,
        string $step,
        string $prompt,
        Exception $exception,
    ): void {
        $stepLabel = Str::headline(str_replace('_', ' ', $step));
        $errorMessage = mb_trim($exception->getMessage());

        $historyPrompt = implode("\n\n", [
            "Local {$stepLabel} fallback (AI call failed).",
            $this->formatLocalHistorySection('Context', [
                'reason' => $this->isAiCallTimeout($exception)
                    ? 'AI request timed out; fallback applied to keep document processing reviewable'
                    : 'AI request failed; fallback applied to keep document processing reviewable',
                'error_message' => $errorMessage,
                'original_prompt' => $prompt,
            ]),
        ]);

        $historyResponse = $this->formatLocalHistorySection('Result', [
            'matched_id' => null,
            'recommended_category_id' => null,
        ]);

        $this->appendProcessingHistory($document, $step, $historyPrompt, $historyResponse, false);
    }

    public function hasAiFailureFallbackHistory(AiDocument $document, string $step): bool
    {
        $history = $document->ai_chat_history;
        if (! is_array($history)) {
            return false;
        }

        foreach (array_reverse($history) as $entry) {
            if (($entry['step'] ?? null) !== $step) {
                continue;
            }

            $prompt = (string) ($entry['prompt'] ?? '');

            return Str::contains($prompt, 'fallback (AI call failed)');
        }

        return false;
    }

    public function isAiCallTimeout(Exception $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return Str::contains($message, ['curl error 28', 'operation timed out', 'timed out']);
    }

    private function buildLocalHistoryPrompt(string $step, array $context): string
    {
        $stepLabel = Str::headline(str_replace('_', ' ', $step));

        return implode("\n\n", [
            "Local {$stepLabel} decision (AI call skipped).",
            $this->formatLocalHistorySection('Context', $context),
        ]);
    }

    private function buildLocalHistoryResponse(array $result): string
    {
        return $this->formatLocalHistorySection('Result', $result);
    }

    private function formatLocalHistorySection(string $title, array $payload): string
    {
        $lines = ["{$title}:"];
        $flattenedPayload = $this->flattenLocalHistoryPayload($payload);

        if ($flattenedPayload === []) {
            $lines[] = '- None';

            return implode("\n", $lines);
        }

        foreach ($flattenedPayload as $key => $value) {
            $label = Str::headline(str_replace('.', ' ', (string) $key));
            $lines[] = "- {$label}: {$this->formatLocalHistoryValue($value)}";
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function flattenLocalHistoryPayload(array $payload, string $prefix = ''): array
    {
        $flattenedPayload = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                if ($value === []) {
                    $flattenedPayload[$path] = [];
                } else {
                    $flattenedPayload = [
                        ...$flattenedPayload,
                        ...$this->flattenLocalHistoryPayload($value, $path),
                    ];
                }

                continue;
            }

            $flattenedPayload[$path] = $value;
        }

        return $flattenedPayload;
    }

    private function formatLocalHistoryValue(mixed $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            $trimmedValue = mb_trim($value);

            return $trimmedValue === '' ? '(empty)' : $trimmedValue;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return '[unavailable]';
        }

        return $encoded;
    }
}
