<?php

use App\Ai\Support\ByoProviderMigrator;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Data migration: convierte el BYO legacy de cada usuario con url + key en
     * un `AiProvider` "Principal" y su fila `global` en `ai_scopes`.
     */
    public function up(): void
    {
        User::query()
            ->whereNotNull('ai_provider_url')
            ->whereNotNull('ai_provider_key')
            ->eachById(function (User $user): void {
                try {
                    ByoProviderMigrator::migrate($user);
                } catch (DecryptException) {
                    // Una key legacy que no se puede descifrar con el APP_KEY
                    // actual no debe abortar el despliegue: se salta ese
                    // usuario, sus columnas `users.ai_*` siguen intactas y
                    // puede reconfigurar su proveedor en Settings → IA.
                    Log::warning('BYO provider migration skipped: legacy ai_provider_key is not decryptable.', [
                        'user_id' => $user->getKey(),
                    ]);
                }
            });
    }

    /**
     * No-op: las columnas legacy `users.ai_*` se conservan y el registro
     * derivado se gestiona desde Settings → IA.
     */
    public function down(): void {}
};
