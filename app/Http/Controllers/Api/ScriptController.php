<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreScriptRequest;
use App\Http\Requests\UpdateScriptRequest;
use App\Http\Resources\ScriptResource;
use App\Jobs\GenerateScriptJob;
use App\Models\Script;
use Illuminate\Http\Request;


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
        if ($script->status === 'processing') {
            return response()->json([
                'message' => 'O roteiro está sendo gerado. Aguarde a conclusão para atualizar.',
            ], 409);
        }

        $script->fill($request->validated());

        $script->forceFill([
            'generated_content' => null,
            'error_message'     => null,
            'status'            => 'pending',
        ]);

        $script->save();

        GenerateScriptJob::dispatch($script);

        return new ScriptResource($script->refresh());
    }

    public function destroy(Script $script)
    {
        $script->delete();
        return response(null, 204);
    }
}
