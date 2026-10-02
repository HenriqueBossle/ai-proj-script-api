<?php

namespace App\Services;

use App\Models\Script;
use App\Neuron\ScriptGeneratorAgent;
use NeuronAI\Chat\Messages\UserMessage;

class ScriptGeneratorService
{
    public function generate(Script $script): string
    {
        $sourceText = $script->source_text
            ?: 'Nenhuma fonte factual foi fornecida.';

        $styleText = $script->style_text
            ?: 'Escreva de forma natural, clara e envolvente para vídeo.';

        $prompt = <<<PROMPT
Crie um roteiro para o seguinte vídeo.

TOPIC:
{$script->topic}

SOURCE_TEXT:
{$sourceText}

STYLE_TEXT:
{$styleText}

Lembre-se:

- topic define o assunto.
- source_text é a única fonte factual.
- style_text define somente o estilo.
- Não invente informações.
- Não utilize fatos externos.
- Retorne somente o roteiro final.
PROMPT;

        $message = ScriptGeneratorAgent::make()
            ->chat(new UserMessage($prompt))
            ->getMessage();

        return trim($message->getContent());
    }
}