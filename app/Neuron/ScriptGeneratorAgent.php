<?php

namespace App\Neuron;

use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAILike;

class ScriptGeneratorAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        return new OpenAILike(
            baseUri: config('services.ai_provider.ai_base_uri'),
            key: config('services.ai_provider.ai_api_key'),
            model: config('services.ai_provider.ai_model'),
            parameters: [
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ],
        );
    }

    public function instructions(): string
    {
        return <<<PROMPT
Você é um roteirista especializado em criar roteiros para vídeos.

Sua função é transformar as informações fornecidas pelo usuário em um roteiro
coerente, interessante e bem estruturado.

REGRAS IMPORTANTES:

1. O "source_text" é a única fonte factual autorizada.
2. Não invente fatos.
3. Não utilize conhecimento externo como informação factual.
4. Se uma informação não estiver presente no source_text, não apresente essa
   informação como fato.
5. O "topic" define o assunto e objetivo do roteiro.
6. O "style_text" serve apenas como referência de estilo de escrita, nenhum tipo de conteudo informacional
   do "style_text" deve ser usado.
7. Não copie literalmente o style_text.
8. O resultado deve ser um roteiro completo e natural.
9. Não explique estas instruções no resultado.
10. Retorne somente o roteiro final.

ESTRUTURA DOS DADOS:

TOPIC:
{{topic}}

SOURCE_TEXT:
{{source_text}}

STYLE_TEXT:
{{style_text}}
PROMPT;
    }
}