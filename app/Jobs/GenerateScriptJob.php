<?php

namespace App\Jobs;

use App\Models\Script;
use App\Services\ScriptGeneratorService;

class GenerateScriptJob extends ScriptGenerationJob
{
    public function __construct(Script $script)
    {
        parent::__construct($script);
    }

    protected function expectedStatus(): string
    {
        return 'pending';
    }

    protected function generateContent(
        Script $script,
        ScriptGeneratorService $generator
    ): string {
        return $generator->generate($script);
    }
}
