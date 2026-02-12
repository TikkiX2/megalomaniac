<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\PurchaseCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        // Create currencies
        $ars = Currency::create([
            'code' => 'ARS',
            'name' => 'Peso Argentino',
            'symbol' => '$',
            'is_active' => true,
        ]);

        $usd = Currency::create([
            'code' => 'USD',
            'name' => 'Dólar Estadounidense',
            'symbol' => 'US$',
            'is_active' => true,
        ]);

        $eur = Currency::create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'is_active' => true,
        ]);

        // Create exchange rates (example rates)
        ExchangeRate::create([
            'from_currency_id' => $ars->id,
            'to_currency_id' => $usd->id,
            'rate' => 0.001, // 1 ARS = 0.001 USD (ejemplo: 1000 ARS = 1 USD)
            'effective_date' => Carbon::now()->subDays(30),
        ]);

        ExchangeRate::create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $ars->id,
            'rate' => 1000, // 1 USD = 1000 ARS
            'effective_date' => Carbon::now()->subDays(30),
        ]);

        ExchangeRate::create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $eur->id,
            'rate' => 0.92, // 1 USD = 0.92 EUR
            'effective_date' => Carbon::now()->subDays(30),
        ]);

        ExchangeRate::create([
            'from_currency_id' => $eur->id,
            'to_currency_id' => $usd->id,
            'rate' => 1.09, // 1 EUR = 1.09 USD
            'effective_date' => Carbon::now()->subDays(30),
        ]);

        // Create default purchase categories
        $categories = [
            ['name' => 'Comida', 'icon' => '🍔', 'color' => '#FF6B6B'],
            ['name' => 'Transporte', 'icon' => '🚗', 'color' => '#4ECDC4'],
            ['name' => 'Entretenimiento', 'icon' => '🎮', 'color' => '#95E1D3'],
            ['name' => 'Salud', 'icon' => '💊', 'color' => '#F38181'],
            ['name' => 'Educación', 'icon' => '📚', 'color' => '#AA96DA'],
            ['name' => 'Servicios', 'icon' => '💡', 'color' => '#FCBAD3'],
            ['name' => 'Ropa', 'icon' => '👕', 'color' => '#A8D8EA'],
            ['name' => 'Tecnología', 'icon' => '💻', 'color' => '#6C5CE7'],
            ['name' => 'Hogar', 'icon' => '🏠', 'color' => '#FD79A8'],
            ['name' => 'Otros', 'icon' => '📦', 'color' => '#B2BEC3'],
        ];

        // Get first user ID (assuming you have at least one user)
        $userId = \App\Models\User::first()?->id ?? 1;

        foreach ($categories as $category) {
            PurchaseCategory::create(array_merge($category, ['user_id' => $userId]));
        }

        $this->command->info('Finance data seeded successfully!');
        $this->command->info('Currencies: ARS, USD, EUR');
        $this->command->info('Exchange rates created');
        $this->command->info('10 purchase categories created');
    }
}
