import { useMemo } from 'react';

interface EvolutionPoint {
    date: string;
    value: number;
    unit?: string;
    flag?: string | null;
    cx?: number;
    cy?: number;
}

interface StudyResultChartProps {
    points: EvolutionPoint[];
    analyte: string;
}

const WIDTH = 640;
const HEIGHT = 200;
const PAD_X = 16;
const PAD_Y = 24;

/**
 * Gráfico de evolución de un analito a lo largo de los estudios del usuario.
 * SVG propio (sin librerías): normaliza los puntos al viewBox y dibuja la
 * polyline + guías de mín/máx + puntos resaltados.
 */
export function StudyResultChart({ points, analyte }: StudyResultChartProps) {
    const geometry = useMemo(() => {
        if (points.length === 0) return null;

        const values = points.map((p) => p.value);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const span = max - min || 1;
        const pad = span * 0.15 + 1e-9;

        const chartMin = min - pad;
        const chartMax = max + pad;

        const x = (i: number) =>
            points.length === 1
                ? WIDTH / 2
                : PAD_X + (i / (points.length - 1)) * (WIDTH - PAD_X * 2);
        const y = (v: number) =>
            PAD_Y + ((chartMax - v) / (chartMax - chartMin)) * (HEIGHT - PAD_Y * 2);

        return {
            min,
            max,
            minY: y(min),
            maxY: y(max),
            points: points.map((p, i) => ({ ...p, cx: x(i), cy: y(p.value) })),
            polyline: points.map((p, i) => `${x(i)},${y(p.value)}`).join(' '),
            last: points[points.length - 1],
        };
    }, [points]);

    if (!geometry) {
        return (
            <p className="py-8 text-center text-sm italic text-muted-foreground">
                Sin puntos para graficar {analyte || 'este analito'}.
            </p>
        );
    }

    const showGuides = geometry.max !== geometry.min;

    return (
        <div className="w-full overflow-x-auto">
            <div className="flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                <span>Evolución · {analyte}</span>
                <span>
                    {geometry.points.length} puntos · último {geometry.last.date}
                </span>
            </div>
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="h-auto w-full text-primary"
                role="img"
                aria-label={`Evolución de ${analyte}`}
            >
                {showGuides && (
                    <>
                        <line
                            x1={PAD_X}
                            x2={WIDTH - PAD_X}
                            y1={geometry.minY}
                            y2={geometry.minY}
                            className="stroke-border"
                            strokeWidth={1}
                            strokeDasharray="4 4"
                        />
                        <line
                            x1={PAD_X}
                            x2={WIDTH - PAD_X}
                            y1={geometry.maxY}
                            y2={geometry.maxY}
                            className="stroke-border"
                            strokeWidth={1}
                            strokeDasharray="4 4"
                        />
                    </>
                )}
                <text x={WIDTH - PAD_X} y={geometry.maxY - 6} textAnchor="end" className="fill-muted-foreground text-[10px]">
                    Máx {geometry.max}
                </text>
                <text x={WIDTH - PAD_X} y={geometry.minY + 14} textAnchor="end" className="fill-muted-foreground text-[10px]">
                    Mín {geometry.min}
                </text>
                <polyline
                    points={geometry.polyline}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />
                {geometry.points.map((p) => (
                    <circle
                        key={`${p.cx}-${p.cy}`}
                        cx={p.cx}
                        cy={p.cy}
                        r={3}
                        className={
                            p.flag === 'high'
                                ? 'fill-destructive'
                                : p.flag === 'low'
                                  ? 'fill-primary'
                                  : 'fill-white/80'
                        }
                    />
                ))}
                <text
                    x={geometry.points[0].cx}
                    y={geometry.points[0].cy - 8}
                    textAnchor="middle"
                    className="fill-muted-foreground text-[10px]"
                >
                    {geometry.points[0].date}
                </text>
                <text
                    x={geometry.last.cx ?? WIDTH / 2}
                    y={(geometry.last.cy ?? 0) + 18}
                    textAnchor="middle"
                    className="fill-white text-[10px] font-bold"
                >
                    {geometry.last.value} {geometry.last.unit}
                </text>
            </svg>
        </div>
    );
}