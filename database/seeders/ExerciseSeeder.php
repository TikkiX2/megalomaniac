<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const EXERCISES = [
        ['Press banca', 'Chest', 'Compound'],
        ['Press banca inclinado', 'Chest', 'Compound'],
        ['Press banca declinado', 'Chest', 'Compound'],
        ['Aperturas con mancuernas', 'Chest', 'Isolation'],
        ['Fondos en paralelas', 'Chest', 'Bodyweight'],
        ['Press militar', 'Shoulders', 'Compound'],
        ['Elevaciones laterales', 'Shoulders', 'Isolation'],
        ['Elevaciones frontales', 'Shoulders', 'Isolation'],
        ['Pájaros con mancuernas', 'Shoulders', 'Isolation'],
        ['Press Arnold', 'Shoulders', 'Compound'],
        ['Dominadas', 'Back', 'Bodyweight'],
        ['Remo con barra', 'Back', 'Compound'],
        ['Remo con mancuerna', 'Back', 'Compound'],
        ['Jalón al pecho', 'Back', 'Machine'],
        ['Peso muerto', 'Back', 'Compound'],
        ['Pullover en polea', 'Back', 'Isolation'],
        ['Sentadilla', 'Legs', 'Compound'],
        ['Sentadilla frontal', 'Legs', 'Compound'],
        ['Prensa de piernas', 'Legs', 'Machine'],
        ['Zancadas', 'Legs', 'Compound'],
        ['Peso muerto rumano', 'Legs', 'Compound'],
        ['Curl femoral', 'Legs', 'Machine'],
        ['Extensión de cuádriceps', 'Legs', 'Machine'],
        ['Elevación de gemelos', 'Legs', 'Isolation'],
        ['Hip thrust', 'Legs', 'Compound'],
        ['Curl de bíceps con barra', 'Arms', 'Isolation'],
        ['Curl de bíceps alterno', 'Arms', 'Isolation'],
        ['Curl martillo', 'Arms', 'Isolation'],
        ['Curl predicador', 'Arms', 'Isolation'],
        ['Extensión de tríceps en polea', 'Arms', 'Isolation'],
        ['Press francés', 'Arms', 'Isolation'],
        ['Fondos en banco', 'Arms', 'Bodyweight'],
        ['Patada de tríceps', 'Arms', 'Isolation'],
        ['Plancha abdominal', 'Core', 'Bodyweight'],
        ['Crunch en polea', 'Core', 'Isolation'],
        ['Elevación de piernas colgado', 'Core', 'Bodyweight'],
        ['Rueda abdominal', 'Core', 'Bodyweight'],
    ];

    public function run(): void
    {
        foreach (self::EXERCISES as [$name, $muscleGroup, $type]) {
            Exercise::firstOrCreate(
                ['name' => $name],
                ['muscle_group' => $muscleGroup, 'type' => $type],
            );
        }
    }
}
