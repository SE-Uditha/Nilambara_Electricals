<?php

namespace App\Modules\Customers\Services;

use App\Enums\CustomerType;
use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, User $actor): Customer
    {
        return DB::transaction(function () use ($data, $actor): Customer {
            $type = CustomerType::from($data['customer_type']);
            $prefix = trim((string) config('business.customer_code_prefix', 'CUS-'));

            if ($prefix === '') {
                throw new \LogicException('The customer code prefix must not be empty.');
            }
            if (strlen($prefix) + 26 > 40) {
                throw new \LogicException('The customer code prefix is too long for the customer ID column.');
            }

            $customer = Customer::query()->create([
                'customer_code' => $prefix.Str::upper((string) Str::ulid()),
                'customer_type' => $type,
                'customer_name' => $type === CustomerType::Individual ? trim($data['customer_name']) : null,
                'company_name' => $type === CustomerType::Company ? trim($data['company_name']) : null,
                'contact_person' => $this->nullableString($data['contact_person'] ?? null),
                'address' => trim($data['address']),
                'mobile_number' => trim($data['mobile_number']),
                'alternate_phone' => $this->nullableString($data['alternate_phone'] ?? null),
                'email' => $this->nullableString($data['email'] ?? null),
                'opening_balance' => $data['opening_balance'] ?? '0.00',
                'special_notes' => $this->nullableString($data['special_notes'] ?? null),
                'registration_date' => $data['registration_date'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $openingBalance = (string) $customer->opening_balance;

            if ((float) $openingBalance !== 0.0) {
                CustomerAccountEntry::query()->create([
                    'customer_id' => $customer->id,
                    'entry_type' => 'opening',
                    'amount' => $openingBalance,
                    'occurred_at' => $customer->registration_date->startOfDay(),
                    'reference_number' => $customer->customer_code,
                    'description' => 'Opening balance recorded at customer registration.',
                    'recorded_by_user_id' => $actor->id,
                    'idempotency_key' => (string) Str::uuid(),
                ]);
            }

            $this->audit($actor, $customer, 'customer.created', null, [
                'customer_code' => $customer->customer_code,
                'customer_type' => $type->value,
                'opening_balance' => $openingBalance,
                'is_active' => $customer->is_active,
            ]);

            return $customer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data, User $actor): Customer
    {
        return DB::transaction(function () use ($customer, $data, $actor): Customer {
            $before = [
                'customer_type' => $customer->customer_type->value,
                'display_name' => $customer->display_name,
                'is_active' => $customer->is_active,
            ];
            $type = CustomerType::from($data['customer_type']);

            $customer->fill([
                'customer_type' => $type,
                'customer_name' => $type === CustomerType::Individual ? trim($data['customer_name']) : null,
                'company_name' => $type === CustomerType::Company ? trim($data['company_name']) : null,
                'contact_person' => $this->nullableString($data['contact_person'] ?? null),
                'address' => trim($data['address']),
                'mobile_number' => trim($data['mobile_number']),
                'alternate_phone' => $this->nullableString($data['alternate_phone'] ?? null),
                'email' => $this->nullableString($data['email'] ?? null),
                'special_notes' => $this->nullableString($data['special_notes'] ?? null),
            ])->save();

            $after = [
                'customer_type' => $customer->customer_type->value,
                'display_name' => $customer->display_name,
                'is_active' => $customer->is_active,
            ];

            $this->audit($actor, $customer, 'customer.updated', $before, $after);

            return $customer->refresh();
        });
    }

    public function setActive(Customer $customer, bool $active, User $actor): void
    {
        if ($customer->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($customer, $active, $actor): void {
            $before = ['is_active' => $customer->is_active];
            $customer->forceFill(['is_active' => $active])->save();

            $this->audit(
                $actor,
                $customer,
                $active ? 'customer.activated' : 'customer.deactivated',
                $before,
                ['is_active' => $active],
            );
        });
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function audit(
        User $actor,
        Customer $customer,
        string $action,
        ?array $oldValues,
        array $newValues,
    ): void {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'entity_type' => Customer::class,
            'entity_id' => $customer->id,
            'occurred_at' => now(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'request_id' => app()->runningInConsole() ? null : request()->header('X-Request-Id'),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
