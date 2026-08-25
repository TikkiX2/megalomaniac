import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import type { PersonalTask } from '@/types/personal';

interface Props {
    tasks: PersonalTask[];
    onTaskClick?: (task: PersonalTask) => void;
    onDateClick?: (date: string) => void;
}

export default function TaskCalendar({ tasks, onTaskClick, onDateClick }: Props) {
    const [current, setCurrent] = useState(() => new Date());

    const year = current.getFullYear();
    const month = current.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    // Adjust: Monday=0
    const startOffset = firstDay === 0 ? 6 : firstDay - 1;

    const days: (number | null)[] = Array(startOffset).fill(null);
    for (let d = 1; d <= daysInMonth; d++) days.push(d);
    while (days.length % 7 !== 0) days.push(null);

    const getTasksForDay = (day: number) => {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        return tasks.filter(t => {
            const due = t.due_date ? t.due_date.slice(0, 10) : null;
            const start = t.start_date ? t.start_date.slice(0, 10) : null;
            return due === dateStr || start === dateStr;
        });
    };

    const isToday = (day: number) => {
        const now = new Date();
        return now.getFullYear() === year && now.getMonth() === month && now.getDate() === day;
    };

    const monthName = current.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });

    return (
        <div className="flex flex-col gap-4 bg-card border border-border rounded-xl p-4">
            <div className="flex items-center justify-between">
                <h3 className="font-bold capitalize">{monthName}</h3>
                <div className="flex gap-1">
                    <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setCurrent(new Date(year, month - 1, 1))}><ChevronLeft className="h-4 w-4" /></Button>
                    <Button variant="outline" size="sm" onClick={() => setCurrent(new Date())}>Hoy</Button>
                    <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setCurrent(new Date(year, month + 1, 1))}><ChevronRight className="h-4 w-4" /></Button>
                </div>
            </div>

            <div className="grid grid-cols-7 gap-px bg-border rounded-lg overflow-hidden">
                {['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].map(d => (
                    <div key={d} className="bg-muted py-2 text-center text-[10px] font-black uppercase tracking-widest text-muted-foreground">{d}</div>
                ))}
                {days.map((day, idx) => (
                    <div
                        key={idx}
                        className={`min-h-24 p-1 flex flex-col gap-1 ${day == null ? 'bg-muted/30' : 'bg-card hover:bg-accent cursor-pointer'} ${day != null && isToday(day) ? 'bg-primary/5 border border-primary/20' : ''}`}
                        onClick={() => day != null && onDateClick?.(`${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`)}
                    >
                        {day != null && (
                            <>
                                <span className={`text-xs font-bold h-6 w-6 flex items-center justify-center rounded-full ${isToday(day) ? 'bg-primary text-primary-foreground' : ''}`}>{day}</span>
                                <div className="flex flex-col gap-0.5">
                                    {getTasksForDay(day).slice(0, 3).map(t => (
                                        <button
                                            key={t.id}
                                            onClick={(e) => { e.stopPropagation(); onTaskClick?.(t); }}
                                            className="text-[10px] leading-tight truncate text-left px-1 py-0.5 rounded bg-primary/20 text-primary font-medium hover:bg-primary/30"
                                        >
                                            {t.title}
                                        </button>
                                    ))}
                                    {getTasksForDay(day).length > 3 && (
                                        <span className="text-[10px] text-muted-foreground">+{getTasksForDay(day).length - 3} más</span>
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
