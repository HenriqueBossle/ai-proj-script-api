<?php

namespace App\Models;

use Database\Factories\ScriptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Script extends Model
{
    /** @use HasFactory<ScriptFactory> */
    use HasFactory;

    /**
     * Campos que podem ser atribuídos em massa.
     *
     * Nota: generated_content, status e error_message são incluídos aqui
     * pois o GenerateScriptJob os atualiza via update(). O controller não
     * confia em valores desses campos enviados pelo cliente — o user_id
     * é sempre obtido do usuário autenticado, e os campos de geração são
     * gerenciados exclusivamente pelo Job.
     */
    protected $fillable = [
        'user_id',
        'topic',
        'source_text',
        'style_text',
        'generated_content',
        'status',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
