# Módulo Gym

Models: Exercise, Routine, RoutineExercise, Workout, WorkoutExercise, WorkoutSet

Rutas: `gym/exercises`, `gym/routines`, `gym/workouts`, `gym/workouts/{id}/exercises`, `gym/workout-exercises/{id}/sets`, `fitness/gym`, `fitness/routines`, `fitness/gym-routine`

Pages: `fitness/gym-routine.tsx` (461 líneas), `fitness/routines.tsx`, `fitness/dashboard.tsx`, `layouts/gym-layout.tsx`

Flujo diario: crear workout → añadir exercise desde quick library → log sets (kg/reps/RPE) → complete → finish → timer → volume/sets footer.

QA diario: verificar timer, previous set, add Set, finish disabled sin workout, suggestedRoutine CTA, quick library filter.

Feature plan: PR Timeline (best set por exercise), Volume Chart (chart-1 rojizo), Streaks heatmap.
