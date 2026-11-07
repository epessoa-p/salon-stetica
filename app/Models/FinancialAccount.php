<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

/** Cuenta de la empresa (caja chica, banco…). Su saldo es la suma de sus movimientos. */
class FinancialAccount extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'name', 'type', 'bank_name', 'account_number', 'notes', 'active', 'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public const TYPES = [
        'capital'    => 'Capital',
        'caja_chica' => 'Caja chica',
        'banco'      => 'Banco',
        'billetera'  => 'Billetera digital',
        'otra'       => 'Otra',
    ];

    public const ICONS = [
        'capital'    => 'bi-safe',
        'caja_chica' => 'bi-piggy-bank',
        'banco'      => 'bi-bank',
        'billetera'  => 'bi-phone',
        'otra'       => 'bi-wallet2',
    ];

    /** Validación de los datos de la cuenta, compartida por la web y la API. */
    public static function rules(int $companyId, ?int $ignoreId = null): array
    {
        return [
            'name'           => ['required', 'string', 'max:120', Rule::unique('financial_accounts', 'name')->where('company_id', $companyId)->whereNull('deleted_at')->ignore($ignoreId)],
            'type'           => ['required', Rule::in(array_keys(self::TYPES))],
            'bank_name'      => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'notes'          => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function messages(): array
    {
        return ['name.required' => 'Escribe el nombre de la cuenta.', 'name.unique' => 'Ya existe una cuenta con ese nombre.'];
    }

    /** Nombre en mayúsculas; el banco y el número aplican a bancos y billeteras. */
    public static function clean(array $data): array
    {
        $data['name'] = mb_strtoupper(trim($data['name']));
        if (!in_array($data['type'], ['banco', 'billetera'], true)) {
            $data['bank_name'] = null;
            $data['account_number'] = null;
        }

        return $data;
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AccountMovement::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function icon(): string
    {
        return self::ICONS[$this->type] ?? 'bi-wallet2';
    }

    public function balance(): float
    {
        return round((float) $this->movements()->whereNull('voided_at')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as saldo")
            ->value('saldo'), 2);
    }

    /** Agrega el saldo como columna `saldo`, para listados sin una consulta por cuenta. */
    public function scopeWithBalance(Builder $query): Builder
    {
        return $query->addSelect(['saldo' => AccountMovement::query()
            ->withoutGlobalScopes()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0)")
            ->whereColumn('financial_account_id', 'financial_accounts.id')
            ->whereNull('voided_at')]);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
