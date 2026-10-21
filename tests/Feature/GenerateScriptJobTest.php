<?php

namespace Tests\Feature;

use App\Jobs\GenerateScriptJob;
use App\Models\Script;
use App\Models\User;
use App\Services\ScriptGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateScriptJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_ignora_script_com_status_diferente_de_pending(): void
    {
        $script = Script::factory()->processing()->create();

        $mockService = Mockery::mock(ScriptGeneratorService::class);
        $mockService->shouldNotReceive('generate');

        $job = new GenerateScriptJob($script);
        $job->handle($mockService);

        // Status não deve ter mudado
        $this->assertSame('processing', $script->fresh()->status);
    }

    #[Test]
    public function job_ignora_se_script_foi_excluido_da_fila(): void
    {
        $script = Script::factory()->create();
        $scriptId = $script->id;

        // Exclui o script antes do job rodar
        $script->delete();

        $mockService = Mockery::mock(ScriptGeneratorService::class);
        $mockService->shouldNotReceive('generate');

        $job = new GenerateScriptJob($script);
        $job->handle($mockService);

        // Não deve existir no banco (e não deve ter lançado exceção)
        $this->assertDatabaseMissing('scripts', ['id' => $scriptId]);
    }

    #[Test]
    public function job_de_sucesso_leva_a_done_e_salva_generated_content(): void
    {
        $script = Script::factory()->create(['status' => 'pending']);

        $mockService = Mockery::mock(ScriptGeneratorService::class);
        $mockService->shouldReceive('generate')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $script->id))
            ->andReturn('Roteiro gerado com sucesso.');

        $job = new GenerateScriptJob($script);
        $job->handle($mockService);

        $script->refresh();
        $this->assertSame('done', $script->status);
        $this->assertSame('Roteiro gerado com sucesso.', $script->generated_content);
        $this->assertNull($script->error_message);
    }

    #[Test]
    public function job_de_falha_leva_a_failed_e_salva_error_message(): void
    {
        $script = Script::factory()->create(['status' => 'pending']);

        $mockService = Mockery::mock(ScriptGeneratorService::class);
        $mockService->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('Erro de comunicação com a IA.'));

        $job = new GenerateScriptJob($script);

        try {
            $job->handle($mockService);
        } catch (\RuntimeException) {
            // O job relança a exceção — comportamento esperado
        }

        $script->refresh();
        $this->assertSame('failed', $script->status);
        $this->assertNotNull($script->error_message);
    }

    #[Test]
    public function error_message_nao_expoe_api_key(): void
    {
        // Configurar uma key falsa para o teste
        config(['services.ai_provider.ai_api_key' => 'sk-secret-key-test-1234']);

        $script = Script::factory()->create(['status' => 'pending']);

        $apiKey = config('services.ai_provider.ai_api_key');

        $mockService = Mockery::mock(ScriptGeneratorService::class);
        $mockService->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException("Authorization: Bearer {$apiKey} is invalid"));

        $job = new GenerateScriptJob($script);

        try {
            $job->handle($mockService);
        } catch (\Throwable) {
            // Esperado — o job relança a exceção
        }

        $script->refresh();
        $this->assertSame('failed', $script->status);
        $this->assertStringNotContainsString($apiKey, (string) $script->error_message);
        $this->assertStringNotContainsString('sk-secret-key-test-1234', (string) $script->error_message);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
