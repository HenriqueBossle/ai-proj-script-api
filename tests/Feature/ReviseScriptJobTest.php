<?php

namespace Tests\Feature;

use App\Jobs\ReviseScriptJob;
use App\Models\Script;
use App\Services\ScriptGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviseScriptJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_salva_a_nova_versao_somente_apos_revisao_bem_sucedida(): void
    {
        $script = Script::factory()->processing()->create([
            'generated_content' => 'Versão anterior.',
        ]);

        $generator = Mockery::mock(ScriptGeneratorService::class);
        $generator->shouldReceive('revise')
            ->once()
            ->with(
                Mockery::on(fn (Script $currentScript) => $currentScript->id === $script->id
                    && $currentScript->generated_content === 'Versão anterior.'
                ),
                'Melhore a introdução.'
            )
            ->andReturn('Versão revisada.');

        (new ReviseScriptJob($script, 'Melhore a introdução.'))->handle($generator);

        $script->refresh();
        $this->assertSame('done', $script->status);
        $this->assertSame('Versão revisada.', $script->generated_content);
        $this->assertNull($script->error_message);
    }

    #[Test]
    public function job_preserva_versao_anterior_quando_a_revisao_falha(): void
    {
        $script = Script::factory()->processing()->create([
            'generated_content' => 'Versão anterior.',
        ]);

        $generator = Mockery::mock(ScriptGeneratorService::class);
        $generator->shouldReceive('revise')
            ->once()
            ->andThrow(new \RuntimeException('Falha de comunicação com a IA.'));

        try {
            (new ReviseScriptJob($script, 'Amplie a conclusão.'))->handle($generator);
            $this->fail('A exceção da IA deveria ser relançada pelo job.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha de comunicação com a IA.', $exception->getMessage());
        }

        $script->refresh();
        $this->assertSame('failed', $script->status);
        $this->assertSame('Versão anterior.', $script->generated_content);
        $this->assertSame('Falha de comunicação com a IA.', $script->error_message);
    }

    #[Test]
    public function job_sinaliza_falha_final_sem_apagar_a_versao_anterior(): void
    {
        $script = Script::factory()->processing()->create([
            'generated_content' => 'Versão anterior.',
        ]);

        (new ReviseScriptJob($script, 'Revise.'))->failed(
            new \RuntimeException('Falha definitiva na fila.')
        );

        $script->refresh();
        $this->assertSame('failed', $script->status);
        $this->assertSame('Versão anterior.', $script->generated_content);
        $this->assertSame('Falha definitiva na fila.', $script->error_message);
    }

    #[Test]
    public function job_sanitiza_credenciais_em_erros_de_revisao(): void
    {
        config(['services.ai_provider.ai_api_key' => 'test-secret-key']);

        $script = Script::factory()->processing()->create([
            'generated_content' => 'Versão anterior.',
        ]);

        $generator = Mockery::mock(ScriptGeneratorService::class);
        $generator->shouldReceive('revise')
            ->once()
            ->andThrow(new \RuntimeException('Authorization: Bearer test-secret-key'));

        try {
            (new ReviseScriptJob($script, 'Revise.'))->handle($generator);
            $this->fail('A exceção da IA deveria ser relançada pelo job.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Authorization: Bearer test-secret-key', $exception->getMessage());
        }

        $script->refresh();
        $this->assertSame('failed', $script->status);
        $this->assertSame('Versão anterior.', $script->generated_content);
        $this->assertStringNotContainsString('test-secret-key', $script->error_message);
    }
}
