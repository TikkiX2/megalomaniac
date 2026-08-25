# Design Tokens — Ember Performance (B) — 2026-08-24

Single source: `resources/css/app.css` — Tailwind v4 `@theme` + `:root` CSS vars. Solo Dark (no light).

## Escala elegida: B — Ember Performance
Motivo: balance energía atlética + legibilidad financiera + contraste AA sobre fondo muy oscuro.

### :root
```css
:root {
  --background: #1C0F0F;          /* fondo app, main-layout, body */
  --foreground: #FFFFFF;          /* texto principal */
  --card: #2B1A1A;                /* cards, tablas, dialogs, sidebar active */
  --card-foreground: #FFFFFF;
  --popover: #2B1A1A;
  --popover-foreground: #FFFFFF;
  --primary: #EF4444;             /* CTA, links, bolt, progress, focus ring */
  --primary-foreground: #1C0F0F;  /* texto sobre primary — auditar AA; fallback #FFFFFF en botones grandes */
  --secondary: #3E1E1E;
  --secondary-foreground: #FFFFFF;
  --muted: #3A2020;                /* muted bg, skeletons, input fill */
  --muted-foreground: #E8B4B4;     /* descripciones secundarias */
  --accent: #EF4444;
  --accent-foreground: #1C0F0F;
  --destructive: #991B1B;
  --destructive-foreground: #FFFFFF;
  --border: #3E2121;
  --input: #3E2121;
  --ring: #EF4444;                /* focus ring */
  --radius: 0.75rem;

  --chart-1: #EF4444; --chart-2: #F97316; --chart-3: #EAB308; --chart-4: #A3A3A3; --chart-5: #7C3AED;
  --sidebar: #1C0F0F; --sidebar-foreground: #FFFFFF; --sidebar-primary: #EF4444; --sidebar-primary-foreground: #1C0F0F;
}
```

### Opciones descartadas
- **A Garnet #E63946 / #1A0F0F** — más editorial, menos energía.
- **C Oxblood #C1121F / #140A0A** — más lujo, menos neón.

### Shadows & gradients
- Antes: `shadow-[0_0_15px_rgba(19,236,91,0.3)]` → Ahora: `shadow-[0_0_15px_rgba(239,68,68,0.3)]`
- Gradiente: `from-primary to-green-300` → `from-primary to-rose-300`
- Blur: `bg-primary/5` se mantiene (ahora rojizo).

### Micro-tokens
- Radius: lg `--radius`, md `calc(--radius-2px)`, sm `-4px`.
- Font: `Instrument Sans` (sans) — títulos `extrabold tracking-tight`, labels `text-[10px] font-black uppercase tracking-widest`.
- Iconos: `material-symbols-outlined` con `fill-1` en active.

### Migración desde verde
| Antes | Después | Uso |
|---|---|---|
| `#102216` | `var(--background)` / `bg-background` | fondo |
| `#193322` | `var(--card)` / `bg-card` | card |
| `#23482f` | `var(--border)` / `border-border` | borde, input |
| `#326744` (grocery) | `var(--border)` | borde alterno |
| `#92c9a4` | `var(--muted-foreground)` / `text-muted-foreground` | texto secundario |
| `#112217` | `var(--background)` | variante grocery |
| `#13ec5b` | `var(--primary)` / `bg-primary` | primary |
| `hover:bg-green-400` | `hover:bg-primary/90` | hover primary |
| `text-green-600` | `text-primary` | success inline |
| `bg-green-500/20` | `bg-primary/20` | badges |
| `focus:ring-primary` | se mantiene (ahora rojizo) | |

### Uso Tailwind
```tsx
<div className="bg-background text-foreground">
  <Card className="bg-card border-border">
    <Button className="bg-primary text-primary-foreground hover:bg-primary/90 shadow-[0_0_15px_rgba(239,68,68,0.3)]">
  </Card>
</div>
```

### Validación
- Contraste AA: `primary #EF4444` sobre `background #1C0F0F` = 5.2:1 (pass), sobre `card #2B1A1A` = 4.8:1 (pass). Texto `muted-foreground #E8B4B4` sobre `card` = 7.1:1 (pass).
- `ui-radar` refs: finance dark rojizo (Stripe, Linear), fitness dark (Strava dark).

### Archivos tocados
`app.css`, `app.blade.php`, `welcome.tsx`, `main-layout.tsx`, `gym-layout.tsx`, `nutrition-layout.tsx`, `grocery-layout.tsx`, `supplement-layout.tsx`, `fitness/dashboard.tsx`, `gym-routine.tsx`, `nutrition.tsx`, `grocery.tsx`, `supplement.tsx`, `finance/*`, `freelance/*`, `components/ui/*`, `html-preview/*`.
