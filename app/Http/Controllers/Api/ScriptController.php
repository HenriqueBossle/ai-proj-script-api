<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ReviseScriptRequest;
use App\Http\Requests\StoreScriptRequest;
use App\Http\Requests\UpdateScriptRequest;
use App\Http\Resources\ScriptResource;
use App\Jobs\GenerateScriptJob;
use App\Jobs\ReviseScriptJob;
use App\Jobs\ScriptGenerationJob;
use App\Models\Script;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ScriptController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Script::class, 'script');
    }

    public function index(Request $request)
    {
        $scripts = $request->user()->scripts()->latest()->get();

        return ScriptResource::collection($scripts);
    }

    public function store(StoreScriptRequest $request)
    {
        $data = $request->validated();

        $data['user_id'] = $request->user()->id;

        $script = Script::create($data);

        GenerateScriptJob::dispatch($script);

        return new ScriptResource($script->refresh());
    }

    public function show(Script $script)
    {
        return new ScriptResource($script);
    }

    public function update(UpdateScriptRequest $request, Script $script)
    {
        $result = DB::transaction(function () use ($request, $script) {
            $lockedScript = Script::query()
                ->lockForUpdate()
                ->findOrFail($script->id);

            if ($lockedScript->status === 'processing') {
                return null;
            }

            $lockedScript->fill($request->validated());

            $lockedScript->forceFill([
                'generated_content' => null,
                'error_message' => null,
                'status' => 'pending',
            ]);

            $lockedScript->save();

            return $lockedScript;
        });

        if (! $result) {
            return response()->json([
                'message' => 'O roteiro está sendo gerado. Aguarde a conclusão para atualizar.',
            ], 409);
        }

        $script = $result;
        GenerateScriptJob::dispatch($script);

        return new ScriptResource($script->refresh());
    }

    public function revise(ReviseScriptRequest $request, Script $script)
    {
        $this->authorize('update', $script);

        $result = DB::transaction(function () use ($script) {
            $lockedScript = Script::query()
                ->lockForUpdate()
                ->findOrFail($script->id);

            if ($lockedScript->status === 'processing') {
                return [
                    'status' => 409,
                    'message' => 'O roteiro está sendo processado. Aguarde a conclusão antes de solicitar outra revisão.',
                ];
            }

            if (empty(trim((string) $lockedScript->generated_content))) {
                return [
                    'status' => 409,
                    'message' => 'O roteiro ainda não possui conteúdo gerado para revisão.',
                ];
            }

            $lockedScript->update([
                'status' => 'processing',
                'error_message' => null,
            ]);

            return ['script' => $lockedScript];
        });

        if (isset($result['status'])) {
            return response()->json([
                'message' => $result['message'],
            ], $result['status']);
        }

        $script = $result['script'];

        try {
            ReviseScriptJob::dispatch($script, $request->validated('instruction'));
        } catch (Throwable $exception) {
            $script->update([
                'status' => 'failed',
                'error_message' => ScriptGenerationJob::sanitizeErrorMessage($exception->getMessage()),
            ]);

            return response()->json([
                'message' => 'Não foi possível iniciar a revisão do roteiro. Tente novamente.',
            ], 503);
        }

        return new ScriptResource($script->refresh());
    }

    public function destroy(Script $script)
    {
        $script->delete();

        return response(null, 204);
    }
}
