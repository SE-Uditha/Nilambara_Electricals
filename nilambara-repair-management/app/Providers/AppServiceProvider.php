<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Policies\CustomerPolicy;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Policies\InvoicePolicy;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Policies\PaymentPolicy;
use App\Modules\POS\Models\PosSale;
use App\Modules\POS\Policies\PosSalePolicy;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\Quotations\Policies\QuotationPolicy;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Policies\RepairJobPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerAuthorizationAndAudit();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function registerAuthorizationAndAudit(): void
    {
        Gate::before(function (mixed $user, string $ability, array &$arguments = []): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            if (! $user->hasActiveAccount() || $user->isExplicitlyDeniedPermission($ability)) {
                return false;
            }

            return $user->checkPermissionTo($ability) === true ? true : null;
        });
        Gate::policy(RepairJob::class, RepairJobPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PosSale::class, PosSalePolicy::class);

        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->forceFill(['last_login_at' => now()])->save();
            $this->recordAudit('auth.login.succeeded', $event->user, ['username' => $event->user->username]);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $username = $event->credentials['username'] ?? null;
            $this->recordAudit(
                'auth.login.failed',
                $event->user instanceof User ? $event->user : null,
                ['username' => is_string($username) ? $username : null],
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            $this->recordAudit(
                'auth.logout',
                $event->user instanceof User ? $event->user : null,
                ['username' => $event->user instanceof User ? $event->user->username : null],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function recordAudit(string $action, ?User $user, array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'entity_type' => 'user',
            'entity_id' => $user?->getKey(),
            'occurred_at' => now(),
            'new_values' => $newValues,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'request_id' => app()->runningInConsole() ? null : request()->header('X-Request-Id'),
        ]);
    }
}
