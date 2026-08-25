# Módulos Nutrition / Supplements / Grocery

## Nutrition
Models: Food, MealLog, MealItem. Rutas: `nutrition/foods/search`, `nutrition/foods`, `nutrition/logs`, `nutrition/logs/items`. Page: `nutrition.tsx` (mealTypes breakfast/lunch/dinner/snack). Layout: nutrition-layout.

## Supplements
Models: Supplement, SupplementLog. Rutas: `supplements/items`, `supplements/{id}/log`, `supplements/logs`. Page: `supplement.tsx`. Estado low_stock.

## Grocery
Models: GroceryItem, GroceryPriceHistory. Rutas: `grocery/items`, `grocery/history`, `grocery/bulk-restock`, `grocery/{item}/consume`. Page: `grocery.tsx` (638 líneas). Features: deficitItems, restockData, Dialog add/edit, tabs inventory/history, stats expenditure.

QA diario: date picker, add food, supplement log, grocery search/filter/consume/edit/delete/bulk-restock/history.

Features: AI meal scan, macro ring, price sparkline.
