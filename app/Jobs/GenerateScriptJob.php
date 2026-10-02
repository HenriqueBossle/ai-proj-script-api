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

    public function __construct(public Script $script) {
    }

    public function handle(
        ScriptGeneratorService $generator
    ): void {
        $this->script->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {

            $content = $generator->generate($this->script);

            $this->script->update([
                'content' => $content,
                'status' => 'done',
                'error_message' => null,
            ]);

        } catch (Throwable $exception) {

            $this->script->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}