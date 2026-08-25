<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use App\Models\TaskProperty;
use Illuminate\Http\Request;

class TaskPropertyController extends Controller
{
    public function store(Request $request, ProjectTask $task)
    {
        abort_if($task->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:text,number,date,select,multi_select,checkbox,url,person'],
            'value_text' => ['nullable', 'string'],
            'value_number' => ['nullable', 'numeric'],
            'value_date' => ['nullable', 'date'],
            'value_json' => ['nullable'],
            'value' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        // Handle generic value field
        if (isset($validated['value']) && ! isset($validated['value_text']) && ! isset($validated['value_number']) && ! isset($validated['value_date']) && ! isset($validated['value_json'])) {
            $value = $validated['value'];
            unset($validated['value']);
            switch ($validated['type']) {
                case 'text':
                case 'url':
                case 'person':
                    $validated['value_text'] = is_string($value) ? $value : json_encode($value);
                    break;
                case 'number':
                    $validated['value_number'] = is_numeric($value) ? $value : null;
                    break;
                case 'date':
                    $validated['value_date'] = $value;
                    break;
                case 'select':
                case 'multi_select':
                case 'checkbox':
                    $validated['value_json'] = is_array($value) ? $value : ['value' => $value];
                    break;
            }
        }
        unset($validated['value']);

        // Check duplicate
        if ($task->properties()->where('key', $validated['key'])->exists()) {
            return back()->withErrors(['key' => 'Ya existe una propiedad con ese nombre.'])->withInput();
        }

        $property = $task->properties()->create($validated);

        return back()->with('success', 'Propiedad creada.');
    }

    public function update(Request $request, TaskProperty $property)
    {
        abort_if($property->task->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:text,number,date,select,multi_select,checkbox,url,person'],
            'value_text' => ['nullable', 'string'],
            'value_number' => ['nullable', 'numeric'],
            'value_date' => ['nullable', 'date'],
            'value_json' => ['nullable'],
            'value' => ['nullable'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (isset($validated['value'])) {
            $value = $validated['value'];
            unset($validated['value']);
            $type = $validated['type'] ?? $property->type;
            switch ($type) {
                case 'text':
                case 'url':
                case 'person':
                    $validated['value_text'] = is_string($value) ? $value : json_encode($value);
                    break;
                case 'number':
                    $validated['value_number'] = is_numeric($value) ? $value : null;
                    break;
                case 'date':
                    $validated['value_date'] = $value;
                    break;
                default:
                    $validated['value_json'] = is_array($value) ? $value : ['value' => $value];
                    break;
            }
        }

        $property->update($validated);

        return back()->with('success', 'Propiedad actualizada.');
    }

    public function destroy(TaskProperty $property)
    {
        abort_if($property->task->user_id !== auth()->id(), 403);

        $property->delete();

        return back()->with('success', 'Propiedad eliminada.');
    }
}
