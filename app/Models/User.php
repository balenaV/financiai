<?php

namespace App\Models;

use App\Services\DefaultCategoryService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_pending_secret'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * O default da coluna só existe no banco: sem repeti-lo aqui, uma
     * instância recém-criada e ainda não relida (o caso de actingAs, e de
     * qualquer código que use o objeto logo após o create) leria null e
     * seria tratada como conta sem senha utilizável.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'has_usable_password' => true,
    ];

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->settings()->create();
            app(DefaultCategoryService::class)->seed($user);
        });
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function importMappings(): HasMany
    {
        return $this->hasMany(ImportMapping::class);
    }

    public function categoryRules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function debtInstallments(): HasMany
    {
        return $this->hasMany(DebtInstallment::class);
    }

    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class);
    }

    public function creditCardBills(): HasMany
    {
        return $this->hasMany(CreditCardBill::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function investmentOperations(): HasMany
    {
        return $this->hasMany(InvestmentOperation::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function financialGoals(): HasMany
    {
        return $this->hasMany(FinancialGoal::class);
    }

    public function goalContributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function twoFactorRecoveryCodes(): HasMany
    {
        return $this->hasMany(TwoFactorRecoveryCode::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Contas criadas por login social recebem uma senha aleatória que o dono
     * nunca chega a ver — para elas, exigir "current_password" tranca a porta
     * em vez de proteger. Ver App\Services\ReauthenticationService.
     */
    public function hasUsablePassword(): bool
    {
        return (bool) $this->has_usable_password;
    }

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
            'two_factor_secret' => 'encrypted',
            'two_factor_pending_secret' => 'encrypted',
            'has_usable_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
