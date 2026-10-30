<?php

namespace Tests\Feature;

use App\Jobs\GenerateScriptJob;
use App\Jobs\ReviseScriptJob;
use App\Models\Script;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScriptApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // store() — Criação
    // -------------------------------------------------------------------------

    #[Test]
    public function cria_script_com_topic_e_source_text_sem_style_text(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/scripts', [
                'topic' => 'Buracos negros',
                'source_text' => 'Os buracos negros são regiões do espaço com gravidade extrema.',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => 'pending']);
        $response->assertJsonFragment(['generated_content' => null]);
        $response->assertJsonFragment(['style_text' => null]);

        $this->assertDatabaseHas('scripts', [
            'user_id' => $user->id,
            'topic' => 'Buracos negros',
            'status' => 'pending',
            'generated_content' => null,
        ]);

        Queue::assertPushed(GenerateScriptJob::class);
    }

    #[Test]
    public function geracao_de_script_respeita_o_limite_de_requisicoes_por_minuto(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        RateLimiter::clear('generation-minute:'.$user->id);

        $payload = [
            'topic' => 'Buracos negros',
            'source_text' => 'Os buracos negros são regiões do espaço com gravidade extrema.',
        ];

        foreach (range(1, 3) as $attempt) {
            $this->actingAs($user)
                ->postJson('/api/scripts', $payload)
                ->assertCreated();
        }

        $this->actingAs($user)
            ->postJson('/api/scripts', $payload)
            ->assertTooManyRequests();

        $this->actingAs($user)
            ->getJson('/api/scripts')
            ->assertOk();

        Queue::assertPushed(GenerateScriptJob::class, 3);
    }

    #[Test]
    public function criacao_sem_source_text_retorna_422_com_mensagem_pt_br(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/scripts', [
                'topic' => 'Buracos negros',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_text']);
        $response->assertJsonFragment(['source_text' => ['A fonte de informações é obrigatória.']]);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function criacao_sem_topic_retorna_422_com_mensagem_pt_br(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/scripts', [
                'source_text' => 'Alguma fonte',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['topic']);
        $response->assertJsonFragment(['topic' => ['O tema é obrigatório.']]);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function topic_com_mais_de_255_caracteres_retorna_422(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/scripts', [
                'topic' => str_repeat('a', 256),
                'source_text' => 'Fonte válida',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['topic']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function source_text_com_mais_de_20000_caracteres_retorna_422(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/scripts', [
                'topic' => 'Tema válido',
                'source_text' => str_repeat('a', 20001),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_text']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function style_text_com_mais_de_20000_caracteres_retorna_422(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/scripts', [
                'topic' => 'Tema válido',
                'source_text' => 'Fonte válida',
                'style_text' => str_repeat('a', 20001),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['style_text']);

        Queue::assertNothingPushed();
    }

    // -------------------------------------------------------------------------
    // index() — Listagem
    // -------------------------------------------------------------------------

    #[Test]
    public function index_retorna_somente_scripts_do_usuario_autenticado(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $scriptA1 = Script::factory()->done()->create(['user_id' => $userA->id]);
        $scriptA2 = Script::factory()->create(['user_id' => $userA->id]);
        Script::factory()->done()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)
            ->getJson('/api/scripts');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($scriptA1->id, $ids);
        $this->assertContains($scriptA2->id, $ids);
    }

    #[Test]
    public function index_retorna_scripts_do_mais_recente_para_o_mais_antigo(): void
    {
        $user = User::factory()->create();

        $old = Script::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(10),
        ]);
        $new = Script::factory()->create([
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/scripts');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$new->id, $old->id], $ids);
    }

    // -------------------------------------------------------------------------
    // show() / destroy() — Autorização
    // -------------------------------------------------------------------------

    #[Test]
    public function show_de_script_de_outro_usuario_retorna_403(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $script = Script::factory()->create(['user_id' => $userB->id]);

        $this->actingAs($userA)
            ->getJson("/api/scripts/{$script->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function destroy_de_script_de_outro_usuario_retorna_403(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $script = Script::factory()->create(['user_id' => $userB->id]);

        $this->actingAs($userA)
            ->deleteJson("/api/scripts/{$script->id}")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // update() — Regeneração
    // -------------------------------------------------------------------------

    #[Test]
    public function update_reseta_campos_e_despacha_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $script = Script::factory()->done()->create(['user_id' => $user->id]);

        $this->assertNotNull($script->generated_content);

        $response = $this->actingAs($user)
            ->putJson("/api/scripts/{$script->id}", [
                'topic' => 'Novo tema',
                'source_text' => 'Nova fonte factual',
            ]);

        $response->assertStatus(200);

        $script->refresh();
        $this->assertNull($script->generated_content);
        $this->assertNull($script->error_message);
        $this->assertSame('pending', $script->status);
        $this->assertSame('Novo tema', $script->topic);

        Queue::assertPushed(GenerateScriptJob::class);
    }

    #[Test]
    public function update_com_status_processing_retorna_409(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $script = Script::factory()->processing()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->putJson("/api/scripts/{$script->id}", [
                'topic' => 'Novo tema',
                'source_text' => 'Nova fonte factual',
            ]);

        $response->assertStatus(409);
        $response->assertJsonFragment(['message' => 'O roteiro está sendo gerado. Aguarde a conclusão para atualizar.']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function update_de_script_de_outro_usuario_retorna_403(): void
    {
        Queue::fake();

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $script = Script::factory()->create(['user_id' => $userB->id]);

        $this->actingAs($userA)
            ->putJson("/api/scripts/{$script->id}", [
                'topic' => 'Tentativa inválida',
                'source_text' => 'Fonte inválida',
            ])
            ->assertStatus(403);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function revise_preserva_conteudo_atual_e_despacha_job_com_a_instrucao(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $script = Script::factory()->done()->create([
            'user_id' => $user->id,
            'generated_content' => 'Versão anterior do roteiro.',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/scripts/{$script->id}/revise", [
                'instruction' => 'Melhore a introdução sem alterar os fatos.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.generated_content', 'Versão anterior do roteiro.');

        $script->refresh();
        $this->assertSame('processing', $script->status);
        $this->assertSame('Versão anterior do roteiro.', $script->generated_content);
        $this->assertNull($script->error_message);

        Queue::assertPushed(ReviseScriptJob::class, fn (ReviseScriptJob $job) => $job->script->is($script)
            && $job->instruction === 'Melhore a introdução sem alterar os fatos.'
        );
    }

    #[Test]
    public function revise_exige_instrucao_e_respeita_limite_de_caracteres(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $script = Script::factory()->done()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/api/scripts/{$script->id}/revise", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['instruction']);

        $this->actingAs($user)
            ->postJson("/api/scripts/{$script->id}/revise", [
                'instruction' => str_repeat('a', 20001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['instruction']);

        $this->assertSame('done', $script->fresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function revise_rejeita_script_em_processamento_ou_sem_conteudo_anterior(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $processingScript = Script::factory()->processing()->create([
            'user_id' => $user->id,
            'generated_content' => 'Versão existente.',
        ]);
        $emptyScript = Script::factory()->create([
            'user_id' => $user->id,
            'generated_content' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/scripts/{$processingScript->id}/revise", [
                'instruction' => 'Revise.',
            ])
            ->assertConflict();

        $this->actingAs($user)
            ->postJson("/api/scripts/{$emptyScript->id}/revise", [
                'instruction' => 'Revise.',
            ])
            ->assertConflict();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function revise_de_script_de_outro_usuario_retorna_403(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $script = Script::factory()->done()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user)
            ->postJson("/api/scripts/{$script->id}/revise", [
                'instruction' => 'Revise.',
            ])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function revisao_de_script_respeita_o_limite_de_requisicoes_por_minuto(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        RateLimiter::clear('revision-minute:'.$user->id);

        foreach (range(1, 4) as $attempt) {
            $script = Script::factory()
                ->done()
                ->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)
                ->postJson("/api/scripts/{$script->id}/revise", [
                    'instruction' => 'Revise o roteiro.',
                ]);

            if ($attempt < 4) {
                $response->assertOk();
            } else {
                $response->assertTooManyRequests();
            }
        }

        Queue::assertPushed(ReviseScriptJob::class, 3);
    }
}
