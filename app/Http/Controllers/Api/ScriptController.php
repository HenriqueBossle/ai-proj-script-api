<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreScriptRequest;
use App\Http\Requests\UpdateScriptRequest;
use App\Http\Resources\ScriptResource;
use App\Models\Script;
use Laravel\Mcp\Request;


class ScriptController extends Controller
{

    public function __construct()
    {
        $this->authorizeResource(Script::class, 'script');
    }


    public function index(Request $request)
    {
        $scripts = Script::where('user_id',
        $request->user()->id)->get();

        return ScriptResource::collection($scripts);
    }

    public function store(StoreScriptRequest $request){
        
        $data = $request->validated();

        $data['user_id'] = $request->user()->id; 

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
