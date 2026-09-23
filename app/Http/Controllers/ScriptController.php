<?php

namespace App\Http\Controllers;

use App\Models\Script;
use Illuminate\Http\Request;

class ScriptController extends Controller
{
    public function index(){
        $scripts = Script::all();
        return ScriptResource::collection($scripts);
    }

    public function store(StoreScriptRequest $request){
        $data = $request->validated();

        $script = Script::create($data);

        return new ScriptResource($script);
    }

    public function show(Script $script)
    {
        return new ScriptResource($script);   
    }

    public function update(UpdateScriptRequest $request, Script $script){
        $data = $request->validated();

        $script->update($data);
        return new ScriptResource($script);
    }

    public function destroy(Script $script)
    {
        $script->delete();
        return response(null, 204);
    }
}
