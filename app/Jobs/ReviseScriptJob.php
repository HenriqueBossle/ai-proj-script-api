<?php

namespace App\Jobs;

use App\Models\Script;
use App\Services\ScriptGeneratorService;

class ReviseScriptJob extends ScriptGenerationJob
{
    public function __construct(Script $script, public string $instruction)
    {
        parent::__construct($script);
    }

    protected function expectedStatus(): string
    {
        return 'processing';
    }

    protected function generateContent(
        Script $script,
        ScriptGeneratorService $generator
    ): string {
        return $generator->revise($script, $this->instruction);
    }
}
