import { Head } from '@inertiajs/react';
import TodayPanel from '@/components/today/TodayPanel';
import type { TodayPanelProps } from '@/components/today/TodayPanel';

/**
 * /today — el módulo Hoy vive en el dashboard; esta ruta renderiza el mismo
 * panel a pantalla completa (TodayController@index).
 */
export default function TodayIndex(props: TodayPanelProps) {
    return (
        <>
            <Head title="Hoy" />
            <div className="mx-auto w-full max-w-2xl p-4 md:p-6">
                <TodayPanel {...props} />
            </div>
        </>
    );
}
