<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function routines()
    {
        return $this->hasMany(Routine::class);
    }

    public function workouts()
    {
        return $this->hasMany(Workout::class);
    }

    public function mealLogs()
    {
        return $this->hasMany(MealLog::class);
    }

    public function supplements()
    {
        return $this->hasMany(Supplement::class);
    }

    public function supplementLogs()
    {
        return $this->hasMany(SupplementLog::class);
    }

    public function groceryItems()
    {
        return $this->hasMany(GroceryItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Finance Relationships
    |--------------------------------------------------------------------------
    */

    public function incomeSources()
    {
        return $this->hasMany(IncomeSource::class);
    }

    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    public function purchaseCategories()
    {
        return $this->hasMany(PurchaseCategory::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function creditCards()
    {
        return $this->hasMany(CreditCard::class);
    }

    public function debts()
    {
        return $this->hasMany(Debt::class);
    }

    public function withdrawalCategories()
    {
        return $this->hasMany(WithdrawalCategory::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function savingsReserves()
    {
        return $this->hasMany(SavingsReserve::class);
    }

    public function currencyExchanges()
    {
        return $this->hasMany(CurrencyExchange::class);
    }

    public function clients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Project::class);
    }
}
