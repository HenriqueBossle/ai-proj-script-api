<?php

namespace Tests\Unit;

use App\Models\Script;
use App\Services\ScriptGeneratorService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ScriptGeneratorServiceTest extends TestCase
{
    #[Test]
    public function lanca_excecao_quando_source_text_estiver_vazio(): void
    {
        $service = new ScriptGeneratorService;

        $script = new Script;
        $script->id = 1;
        $script->topic = 'Qualquer tema';
        $script->source_text = '';
        $script->style_text = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/source_text/i');

        $service->generate($script);
    }

    #[Test]
    public function lanca_excecao_quando_source_text_for_somente_espacos(): void
    {
        $service = new ScriptGeneratorService;

        $script = new Script;
        $script->id = 1;
        $script->topic = 'Qualquer tema';
        $script->source_text = '   ';
        $script->style_text = null;

        $this->expectException(\RuntimeException::class);
        $service->generate($script);
    }

    #[Test]
    public function lanca_excecao_quando_source_text_for_null(): void
    {
        $service = new ScriptGeneratorService;

        $script = new Script;
        $script->id = 1;
        $script->topic = 'Qualquer tema';
        $script->source_text = null;
        $script->style_text = null;

        $this->expectException(\RuntimeException::class);
        $service->generate($script);
    }

    #[Test]
    public function impede_revisao_sem_source_text(): void
    {
        $service = new ScriptGeneratorService;

        $script = new Script;
        $script->id = 1;
        $script->topic = 'Qualquer tema';
        $script->source_text = ' ';
        $script->generated_content = 'Roteiro anterior.';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/source_text/i');

        $service->revise($script, 'Melhore a introdução.');
    }

    #[Test]
    public function impede_revisao_sem_conteudo_anterior(): void
    {
        $service = new ScriptGeneratorService;

        $script = new Script;
        $script->id = 1;
        $script->topic = 'Qualquer tema';
        $script->source_text = 'Fonte factual.';
        $script->generated_content = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/conteúdo gerado/i');

        $service->revise($script, 'Melhore a introdução.');
    }
}
