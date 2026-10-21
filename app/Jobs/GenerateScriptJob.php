<?php

namespace App\Jobs;

use App\Models\Script;
use App\Services\ScriptGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateScriptJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Script $script)
    {
    }

    public function handle(
        ScriptGeneratorService $generator
    ): void {
        // Recarregar o script do banco para garantir o estado mais atual.
        $script = $this->script->fresh();

        // Guarda de segurança: se o script foi excluído ou já não está pendente,
        // não fazer nada (evita jobs duplicados ou processamento órfão).
        if (! $script || $script->status !== 'pending') {
            return;
        }

        $script->update([
            'status'        => 'processing',
            'error_message' => null,
        ]);

        try {

            $generated_content = $generator->generate($script);

            $script->update([
                'generated_content' => $generated_content,
                'status'            => 'done',
                'error_message'     => null,
            ]);

        } catch (Throwable $exception) {

            $script->update([
                'status'        => 'failed',
                'error_message' => $this->sanitizeErrorMessage($exception->getMessage()),
            ]);

            throw $exception;
        }
    }

    /**
     * Remove credenciais e tokens sensíveis da mensagem de erro antes de persistir.
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Remover o valor da API key configurada
        $apiKey = config('services.ai_provider.ai_api_key', '');
        if ($apiKey) {
            $message = str_replace($apiKey, '[REDACTED]', $message);
        }

        // Remover padrões de Bearer token e chaves do tipo sk-...
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $message);
        $message = preg_replace('/\bsk-[A-Za-z0-9_\-]+/', '[REDACTED]', $message);

        // Limitar tamanho da mensagem para evitar texto enorme no banco
        return mb_substr($message, 0, 1000);
    }
}