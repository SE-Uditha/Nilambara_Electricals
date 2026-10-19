# Architecture overview

## Foundation and scope

Nilambara Repair Management is one Laravel 13 application using a modular monolith architecture. Blade, Livewire, Tailwind CSS, Alpine.js, and Vite provide the interface inside the same application. Eloquent and MySQL provide persistence. The source requirements are the supplied [Business Requirements Document](../../../Repair%20Shop%20Management%20Final.pdf); the [coverage map](../modules/brd-coverage.md) identifies the owning module for each requirement.

Step 1 establishes authentication, an authenticated dashboard, navigation, configuration, and empty module boundaries. Business workflows, tables, CRUD endpoints, reports, and full permissions are reserved for subsequent steps. A module directory is a place for future work, not a claim that its functionality exists.

## Request flow

```text
Browser -> Laravel web middleware -> authentication -> route/controller or Livewire
        -> Form Request / authorization policy
        -> action or service -> Eloquent models -> MySQL
        -> Blade response / Livewire update
```

Laravel's standard `web` middleware provides session and CSRF handling. Fortify and the official Livewire starter kit provide the authentication foundation. Public employee registration is disabled. An operator provisions the first account through the local `app:create-user` Artisan command. This command establishes a login account; it does not establish a future business role or permissions matrix.

## Code organization

The standard Laravel directories remain in place. `app/Http`, `app/Models`, and `app/Providers` hold application-wide HTTP, authentication, and framework integration concerns. `app/Modules` holds business capabilities, each using the following reserved directories:

| Directory | Responsibility when implemented |
| --- | --- |
| `Controllers` | Translate HTTP requests and application results into responses. |
| `Models` | Eloquent persistence, relationships, casts, and local query scopes. |
| `Services` | Business operations that coordinate multiple actions or entities. |
| `Repositories` | Complex or reusable persistence boundaries when justified. |
| `Requests` | Laravel Form Requests for validation and request-level authorization. |
| `Policies` | Entity and operation authorization rules. |
| `Enums` | Stable domain values after their meaning is agreed. |
| `Actions` | Focused application operations with one responsibility. |

All 21 modules currently contain only `.gitkeep` placeholders. No interfaces, repositories, controllers, or models are generated merely to populate these directories. Laravel's existing `App\` PSR-4 mapping resolves future classes such as `App\Modules\RepairJobs\Actions\CompleteRepair` without a new package or autoloader.

Keep business rules in their owning module. A controller or Livewire component may delegate to an action or service; it must not duplicate stock, payment, numbering, or authorization rules. Add repositories only where they offer a concrete persistence boundary beyond Eloquent. Introduce providers, events, or contracts when a real integration needs them.

## Module boundaries

| Area | Modules and ownership |
| --- | --- |
| Customer and repair work | `Customers`, `RepairReceipts`, `RepairJobs`, `JobCards`, `Quotations`, `JobTracking` |
| Sales and collection | `Invoices`, `Payments`, `CreditManagement`, `POS` |
| Stock and procurement | `Inventory`, `Suppliers`, `Purchases` |
| Staff and access | `Employees`, `RolesPermissions` |
| Oversight | `Dashboard`, `Reports`, `AuditLogs` |
| Operations and presentation | `BackupRestore`, `PrintTemplates`, `Settings` |

`RepairJobs` owns the job record and lifecycle. `JobTracking` owns status search and presentation, while later status changes delegate to one job lifecycle operation. `JobCards` owns the job card output rather than a second copy of job facts. `PrintTemplates` owns reusable document layout settings; issuing a receipt or invoice remains the responsibility of its business module.

`Inventory` owns the shared stock ledger for both repairs and POS. `Payments` owns payment records and reversals; `CreditManagement` presents credit balances and collection workflows derived from that source. `Reports` and `Dashboard` read authoritative business records and must not invent parallel financial totals.

## Routing and views

`routes/web.php` contains the application entry points and registers future module route files from `routes/modules/` using Laravel routing facilities. Keep files registered once, and retain the `web` and authentication middleware for business screens. The directory is reserved for real routes added with their implementation; Step 1 has no customer, repair, invoice, stock, or POS CRUD routes.

The application shell uses reusable Blade layout, navigation, and dashboard components. Future module views belong under `resources/views/modules/`. A disabled navigation item represents planned work and must not point to a fake business endpoint.

## Persistence and consistency

Only Laravel's default infrastructure migrations are in Step 1. The business schema and ERD will be designed in [Step 2](../database/README.md). The BRD already requires the following design constraints:

- A receipt and its repair job must be created consistently. Automatic identifiers and manual job numbers require database uniqueness and concurrency-safe generation.
- One invoice can contain several jobs belonging to the same customer. One invoice can receive multiple payments, including allocations of earlier advances.
- Repairs and POS share stock. Approving or issuing a repair part must deduct stock once; returns, cancellation, and reversals require linked compensating transactions.
- Stock and payment changes belonging to the same operation must commit or roll back together. Use database transactions and concurrency controls appropriate to each operation.
- Important financial and stock transactions use controlled cancellation or reversal rather than silent destructive deletion. Status changes record their actor and timestamp.
- Profit estimates depend on costs, discounts, and any agreed expenses; sales totals must not be presented as profit.

These are design constraints for later implementation, not implemented business behavior.

## Configuration and localization

Environment variables configure deployment and credentials. The default business currency is `LKR`, timezone is `Asia/Colombo`, and locale is `en` with English fallback. English and Sinhala translation directories are prepared. Business identity, print defaults, pagination, and date/time formatting are configuration concerns; there is no editable settings UI in Step 1.

Keep `.env`, application keys, database credentials, generated dependencies, and local tool configuration outside version control. Commit `.env.example` and dependency lockfiles. Use UTF-8 and a MySQL `utf8mb4` connection for Sinhala and English content.

## Verification strategy

Foundation tests should exercise login, logout, denied guest access, disabled self-registration, authenticated dashboard rendering, and configuration/routing boundaries. The test suite uses isolated in-memory SQLite and must never target a populated business database. SQLite verification does not establish that a local MySQL account or its credentials work.

Later phases add meaningful workflow and integration tests around transactional invariants, authorization, concurrent numbering and stock use, printed output, backup restoration, and report reconciliation. Keep business acceptance separate from the foundation checks; the BRD's end-to-end acceptance criteria appear on physical PDF page 19.
