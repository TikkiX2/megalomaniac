<?php

namespace App\Integrations\Actions;

final class ParamRules
{
    /**
     * @param  Param[]  $params
     * @return array<string, array<int, string>>
     */
    public static function forParams(array $params): array
    {
        $rules = [];

        foreach ($params as $param) {
            $set = [$param->required ? 'required' : 'nullable'];
            $set[] = match ($param->type) {
                'integer' => 'integer',
                'number' => 'numeric',
                'boolean' => 'boolean',
                'array' => 'array',
                default => 'string',
            };

            if ($param->enum !== null) {
                $set[] = 'in:'.implode(',', $param->enum);
            }

            $rules[$param->name] = $set;
        }

        return $rules;
    }

    /**
     * @param  Param[]  $params
     * @return array<string, string>
     */
    public static function labels(array $params): array
    {
        $labels = [];

        foreach ($params as $param) {
            $labels[$param->name] = $param->description;
        }

        return $labels;
    }
}
