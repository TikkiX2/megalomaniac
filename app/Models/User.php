<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'weight',
        'height',
        'target_weight',
        'ai_provider_url',
        'ai_provider_key',
        'ai_model',
        'ai_embeddings_model',
        'ai_enabled',
        'tavily_api_key',
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
        'tavily_api_key',
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
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
            'target_weight' => 'decimal:2',
            'ai_provider_key' => 'encrypted',
            'ai_enabled' => 'boolean',
            'tavily_api_key' => 'encrypted',
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

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function personalProjects(): HasMany
    {
        return $this->hasMany(Project::class)->where('type', 'personal');
    }

    public function personalTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function boardColumns(): HasMany
    {
        return $this->hasMany(TaskBoardColumn::class)->orderBy('sort_order')->orderBy('id');
    }

    public function taskSavedViews(): HasMany
    {
        return $this->hasMany(TaskSavedView::class);
    }

    public function agentSuggestions(): HasMany
    {
        return $this->hasMany(AgentSuggestion::class);
    }
}
