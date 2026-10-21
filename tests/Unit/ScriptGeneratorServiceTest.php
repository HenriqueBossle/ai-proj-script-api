<?php

namespace Tests\Unit;

use App\Models\Script;
use App\Services\ScriptGeneratorService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ScriptGeneratorServiceTest extends TestCase
{
    #[Test]
    public function lancaExcecaoQuandoSourceTextEstiverVazio(): void
    {
        $service = new ScriptGeneratorService();

        $script = new Script();
        $script->id = 1;
        $script->topic = 'Qualquer tema';
        $script->source_text = '';
        $script->style_text = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/source_text/i');

        $service->generate($script);
    }

    #[Test]
    public function lancaExcecaoQuandoSourceTextForSomenteEspacos(): void
    {
        $service = new ScriptGeneratorService();

        $script = new Script();
        $script->id = 1;
        $script->topic = 'Qualquer tema';
        $script->source_text = '   ';
        $script->style_text = null;

        $this->expectException(\RuntimeException::class);
        $service->generate($script);
    }

    #[Test]
    public function lancaExcecaoQuandoSourceTextForNull(): void
    {
        $service = new ScriptGeneratorService();

        $script = new Script();
        $script->id = 1;
        $script->topic = 'Qualquer tema';
        $script->source_text = null;
        $script->style_text = null;

        $this->expectException(\RuntimeException::class);
        $service->generate($script);
    }
}
