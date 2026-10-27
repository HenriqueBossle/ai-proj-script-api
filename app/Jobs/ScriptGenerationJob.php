<?php

namespace App\Jobs;

use App\Models\Script;
use App\Services\ScriptGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

abstract class ScriptGenerationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Script $script) {}

    abstract protected function expectedStatus(): string;

    abstract protected function generateContent(
        Script $script,
        ScriptGeneratorService $generator
    ): string;

    public function handle(ScriptGeneratorService $generator): void
    {
        $script = $this->script->fresh();

        if (! $script || $script->status !== $this->expectedStatus()) {
            return;
        }

        if ($script->status === 'pending') {
            $script->update([
                'status' => 'processing',
                'error_message' => null,
            ]);
        }

        try {
            $generatedContent = $this->generateContent($script, $generator);

            $script->update([
                'generated_content' => $generatedContent,
                'status' => 'done',
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $script->update([
                'status' => 'failed',
                'error_message' => self::sanitizeErrorMessage($exception->getMessage()),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $script = $this->script->fresh();

        if (! $script || $script->status !== 'processing') {
            return;
        }

        $script->update([
            'status' => 'failed',
            'error_message' => self::sanitizeErrorMessage(
                $exception?->getMessage() ?? 'O processamento do roteiro falhou.'
            ),
        ]);
    }

    public static function sanitizeErrorMessage(string $message): string
    {
        $apiKey = config('services.ai_provider.ai_api_key', '');
        if ($apiKey) {
            $message = str_replace($apiKey, '[REDACTED]', $message);
        }

        $message = preg_replace(
            '/(["\']?(?:authorization|api[_-]?key|access[_-]?token|refresh[_-]?token|token|secret|password)["\']?\s*[:=]\s*["\']?)(?:bearer\s+)?[^\s,;"\']+/i',
            '$1[REDACTED]',
            $message
        ) ?? $message;
        $message = preg_replace('/\bsk-[A-Za-z0-9_\-]+/', '[REDACTED]', $message) ?? $message;

        return mb_substr($message, 0, 1000);
    }
}
