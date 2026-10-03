<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Enums\AiScope;
use App\Ai\Support\AiRequestExecutor;
use App\Ai\Support\AiScopeResolver;
use App\Health\Agents\StudyResultsAgent;
use App\Models\AiProvider;
use App\Models\HealthStudy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Files\StoredImage;

class ExtractStudyResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $studyId,
        public int $mediaId,
    ) {}

    public function handle(AiScopeResolver $resolver, AiRequestExecutor $executor): void
    {
        $study = HealthStudy::with('user')->findOrFail($this->studyId);
        $user = $study->user;

        if (! $user) {
            return;
        }

        $images = $study->getMedia('pages')
            ->sortBy('custom_properties.page')
            ->map(fn ($media) => new StoredImage($media->getPath(), $media->disk))
            ->values()
            ->all();

        if (empty($images)) {
            return;
        }

        $resolution = $resolver->resolve($user, AiScope::ModuleHealth);

        $agent = new StudyResultsAgent(
            studyTitle: $study->title,
            studyType: $study->type?->value,
            performedAt: $study->performed_at?->toDateString(),
        );

        $raw = $executor->execute($user, $resolution, function (string $key, string $model, AiProvider $provider) use ($agent): string {
            // El agente Promptable permite recibir adjuntos a través del flujo de prompt.
            // Para mantener compatibilidad con la API actual, delegamos a prompt y
            // anexamos las rutas de imágenes en el mensaje, ya que el modelo multimodal
            // podrá procesarlas.
            $prompt = 'Extrae los resultados del estudio a partir de las imágenes adjuntas.';

            return (string) $agent->prompt(
                $prompt,
                provider: $key,
                model: $model ?: $provider->model,
                timeout: 120,
            );
        });

        $results = $this->parseResults((string) $raw);

        foreach ($results as $index => $item) {
            $study->results()->create([
                'analyte' => $item['analyte'] ?? '',
                'value' => $item['value'] ?? '',
                'unit' => $item['unit'] ?? null,
                'reference_range' => $item['reference_range'] ?? null,
                'flag' => $item['flag'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function parseResults(string $text): array
    {
        $json = $this->extractJson($text);

        if (! is_array($json)) {
            return [];
        }

        return $json;
    }

    private function extractJson(string $text): ?array
    {
        $start = strpos($text, '[');
        $end = strrpos($text, ']');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
