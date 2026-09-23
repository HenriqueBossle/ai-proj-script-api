<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScriptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'topic' => $this->topic,
            'source_text' => $this->source_text,
            'style_text' => $this->style_text,
            'generated_content' => $this->generated_content,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
