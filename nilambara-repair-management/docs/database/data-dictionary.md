# Data dictionary

## Step 11 additive backup metadata

Migration `2026_10_20_100000_extend_step11_backup_metadata` extends existing `backup_histories`; no new business ledger or foreign key is introduced. Existing history rows and columns are retained.

| Field | Type / default | Meaning |
| --- | --- | --- |
| file_size | BIGINT UNSIGNED, nullable | Completed dump size in bytes. |
| completed_at | TIMESTAMP, nullable | Completion/failure timestamp. |
| deleted_at | TIMESTAMP, nullable | File removed by controlled retention; history remains. This is not deletion of the history row. |
| is_protected | BOOLEAN, false | Manual/safety copies are protected from automatic scheduled retention. |
| verification_status | VARCHAR(32), not_verified | File-integrity or isolated recovery verification state; checksum alone does not prove recovery. |
| cloud_file_id | VARCHAR(255), nullable | Private Google Drive file identity after configured upload. |
| cloud_status | VARCHAR(32), not_configured | Cloud copy state, independently of successful local backup. |
| destination_path | TEXT, nullable | Laravel-encrypted private destination, hidden from model serialization and ordinary history pages. Not an encrypted SQL dump. |

The original user/history/restore relationships remain unchanged. Backup policy uses allowlisted `system_settings` entries; destination values are encrypted and secrets/OAuth credentials remain environment-only. See [Step 11](../development/step-11-summary.md) and [backup procedures](../modules/backup-restore.md).

The original 58 entity tables below are defined by the Step 2 migrations. Later additive workflow migrations add `quotation_history` and `invoice_history` and extend existing tables; those additions are called out in their entries and the schema change summary. Step 8 uses the existing payment, allocation, release, reversal, account-ledger, and print-history schema without adding payment tables. Existing Laravel infrastructure follows in a separate section. Step 3 also adds account and Spatie-compatible columns to existing users and access-control tables. Names, defaults, nullability, keys, and CHECKs are explicit. Monetary values use LKR; no foreign-exchange policy is implied.

MySQL/MariaDB use unsigned BIGINT auto-increment primary keys. Disposable SQLite verification substitutes INTEGER PRIMARY KEY AUTOINCREMENT; its numeric affinity does not certify native DECIMAL precision. All entity tables have Laravel timestamps.

## roles

Module: `RolesPermissions`. Model: [Role](../../app/Modules/RolesPermissions/Models/Role.php).

Source / meaning: BRD pp12-13; Step2 sec15; Step3 Spatie-compatible role catalog.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| code | VARCHAR(64) | No | No default | UNIQUE(code) | Code. Immutable through Eloquent after creation. |
| name | VARCHAR(100) | No | No default | UNIQUE(name, guard_name) | Stable role slug used by authorization; Step 3 backfills it from the prior `code`. |
| description | TEXT | Yes | NULL | — | Description. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| guard_name | VARCHAR(25) | No | web | — | Laravel authorization guard. |
| label | VARCHAR(100) | Yes | NULL | — | Human-readable role label; Step 3 preserves the prior `name` value here. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_roles_e6fb0621`: (code).
- `uq_roles_name_guard`: (name, guard_name).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`code`)) > 0`.
- `LENGTH(TRIM(`name`)) > 0`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## permissions

Module: `RolesPermissions`. Model: [Permission](../../app/Modules/RolesPermissions/Models/Permission.php).

Source / meaning: BRD p13; Step3 Spatie-compatible permission catalog.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| code | VARCHAR(120) | No | No default | UNIQUE(code) | Code. |
| name | VARCHAR(150) | No | No default | UNIQUE(name, guard_name) | Stable dotted permission identifier used by authorization; Step 3 backfills it from the prior `code`. |
| module | VARCHAR(64) | No | No default | — | Module. |
| description | TEXT | Yes | NULL | — | Description. |
| guard_name | VARCHAR(25) | No | web | — | Laravel authorization guard. |
| label | VARCHAR(150) | Yes | NULL | — | Human-readable permission label; Step 3 preserves the prior `name` value here. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_permissions_e6fb0621`: (code).
- `uq_permissions_name_guard`: (name, guard_name).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`code`)) > 0`.
- `LENGTH(TRIM(`name`)) > 0`.
- `LENGTH(TRIM(`module`)) > 0`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## role_user

Module: `RolesPermissions`. Spatie `HasRoles` relation on [User](../../app/Models/User.php).

Source / meaning: BRD p13; existing role membership pivot adapted for Spatie.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT; UNIQUE(user_id,role_id) | User id. |
| role_id | BIGINT UNSIGNED | No | No default | FK → roles.id; RESTRICT; UNIQUE(user_id,role_id) | Role id. |
| model_type | VARCHAR(255) | No | App\Models\User | — | Polymorphic model class for Spatie; this existing application assigns roles to users. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_role_user_721adde8`: (user_id, role_id).
- `ix_role_user_cace4a15`: (user_id).
- `ix_role_user_b36bcfe0`: (role_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:


Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## permission_role

Module: `RolesPermissions`. Spatie `permissions` relation on [Role](../../app/Modules/RolesPermissions/Models/Role.php).

Source / meaning: BRD p13; role-to-permission grants.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| role_id | BIGINT UNSIGNED | No | No default | FK → roles.id; RESTRICT; UNIQUE(role_id,permission_id) | Role id. |
| permission_id | BIGINT UNSIGNED | No | No default | FK → permissions.id; RESTRICT; UNIQUE(role_id,permission_id) | Permission id. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_permission_role_1fd58356`: (role_id, permission_id).
- `ix_permission_role_b36bcfe0`: (role_id).
- `ix_permission_role_3b8b97af`: (permission_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:


Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## permission_user

Module: `RolesPermissions`. Spatie `HasPermissions` relation on [User](../../app/Models/User.php), with the existing explicit effect field.

Source / meaning: BRD p13; per-user permission overrides; explicit deny takes precedence over role grants.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT; UNIQUE(user_id,permission_id) | User id. |
| permission_id | BIGINT UNSIGNED | No | No default | FK → permissions.id; RESTRICT; UNIQUE(user_id,permission_id) | Permission id. |
| model_type | VARCHAR(255) | No | App\Models\User | — | Polymorphic model class for Spatie; this existing application assigns direct permissions to users. |
| effect | VARCHAR(64) | No | allow | — | Effect. Allowed: allow, deny. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_permission_user_6d378d26`: (user_id, permission_id).
- `ix_permission_user_cace4a15`: (user_id).
- `ix_permission_user_3b8b97af`: (permission_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``effect` IN ('allow', 'deny')`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. `effect=allow` grants an individual permission; `effect=deny` explicitly denies it before role grants are evaluated.

## employees

Module: `Employees`. Model: [Employee](../../app/Modules/Employees/Models/Employee.php).

Source / meaning: BRD p12; credentials remain only in users.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| employee_code | VARCHAR(40) | No | No default | UNIQUE(employee_code) | Employee code. Immutable through Eloquent after creation. |
| name | VARCHAR(255) | No | No default | — | Name. |
| address | TEXT | Yes | NULL | — | Address. |
| mobile_number | VARCHAR(30) | Yes | NULL | — | Mobile number. |
| job_title | VARCHAR(100) | Yes | NULL | — | Job title. |
| department | VARCHAR(100) | Yes | NULL | — | Department. |
| date_joined | DATE | Yes | NULL | — | Date joined. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT; UNIQUE(user_id) | User id. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_employees_348a4a98`: (employee_code).
- `uq_employees_cace4a15`: (user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`employee_code`)) > 0`.
- `LENGTH(TRIM(`name`)) > 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## customers

Module: `Customers`. Model: [Customer](../../app/Modules/Customers/Models/Customer.php).

Source / meaning: BRD p4; starred type/name-or-company/address/mobile.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| customer_code | VARCHAR(40) | No | No default | UNIQUE(customer_code) | Step 4 generates configurable `CUS-` prefix plus uppercase ULID; database unique key prevents duplicate identifiers. Immutable through Eloquent after creation. |
| customer_type | VARCHAR(64) | No | No default | — | Customer type. Allowed: individual, company. |
| customer_name | VARCHAR(255) | Yes | NULL | — | Customer name. |
| company_name | VARCHAR(255) | Yes | NULL | — | Company name. |
| contact_person | VARCHAR(255) | Yes | NULL | — | Contact person. |
| address | TEXT | No | No default | — | Address. |
| mobile_number | VARCHAR(30) | No | No default | — | Mobile number. |
| alternate_phone | VARCHAR(30) | Yes | NULL | — | Alternate phone. |
| email | VARCHAR(255) | Yes | NULL | — | Email. |
| opening_balance | DECIMAL(19,2) | No | 0 | — | Original signed opening accounting value; never sales; reconcile to one opening ledger entry Immutable through Eloquent after creation. |
| special_notes | TEXT | Yes | NULL | — | Special notes. |
| registration_date | DATE | No | No default | — | Registration date. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_customers_68db6340`: (customer_code).
- `ix_customers_ac145587`: (customer_name).
- `ix_customers_a76c5cd4`: (company_name).
- `ix_customers_e7e1e7cc`: (mobile_number).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `(`customer_type` = 'individual' AND LENGTH(TRIM(COALESCE(`customer_name`, ''))) > 0) OR (`customer_type` = 'company' AND LENGTH(TRIM(COALESCE(`company_name`, ''))) > 0)`.
- `LENGTH(TRIM(`address`)) > 0`.
- `LENGTH(TRIM(`customer_code`)) > 0`.
- ``customer_type` IN ('individual', 'company')`.
- `LENGTH(TRIM(`mobile_number`)) > 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## suppliers

Module: `Suppliers`. Model: [Supplier](../../app/Modules/Suppliers/Models/Supplier.php).

Source / meaning: BRD p12.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| supplier_code | VARCHAR(40) | No | No default | UNIQUE(supplier_code) | Supplier code. Immutable through Eloquent after creation. |
| name | VARCHAR(255) | No | No default | — | Name. |
| contact_person | VARCHAR(255) | Yes | NULL | — | Contact person. |
| address | TEXT | Yes | NULL | — | Address. |
| mobile_number | VARCHAR(30) | Yes | NULL | — | Mobile number. |
| telephone_number | VARCHAR(30) | Yes | NULL | — | Telephone number. |
| email | VARCHAR(255) | Yes | NULL | — | Email. |
| opening_balance | DECIMAL(19,2) | No | 0 | — | Original supplier payable opening value; not purchase income Immutable through Eloquent after creation. |
| credit_terms | TEXT | Yes | NULL | — | Credit terms. |
| credit_days | INTEGER UNSIGNED | No | 0 | — | Credit days. |
| notes | TEXT | Yes | NULL | — | Notes. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_suppliers_e1183bab`: (supplier_code).
- `ix_suppliers_6ae99955`: (name).
- `ix_suppliers_e7e1e7cc`: (mobile_number).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`supplier_code`)) > 0`.
- `LENGTH(TRIM(`name`)) > 0`.
- ``credit_days` >= 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## spare_part_categories

Module: `Inventory`. Model: [SparePartCategory](../../app/Modules/Inventory/Models/SparePartCategory.php).

Source / meaning: BRD p11 category.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| name | VARCHAR(150) | No | No default | UNIQUE(name) | Name. |
| description | TEXT | Yes | NULL | — | Description. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_spare_part_categories_6ae99955`: (name).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`name`)) > 0`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## spare_part_brands

Module: `Inventory`. Model: [SparePartBrand](../../app/Modules/Inventory/Models/SparePartBrand.php).

Source / meaning: BRD p11 brand; proposed normalized reference.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| name | VARCHAR(150) | No | No default | UNIQUE(name) | Name. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_spare_part_brands_6ae99955`: (name).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`name`)) > 0`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## units_of_measure

Module: `Inventory`. Model: [UnitOfMeasure](../../app/Modules/Inventory/Models/UnitOfMeasure.php).

Source / meaning: BRD p11 units; fractional permission remains open.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| code | VARCHAR(20) | No | No default | UNIQUE(code) | Code. |
| name | VARCHAR(100) | No | No default | — | Name. |
| quantity_scale | INTEGER UNSIGNED | No | 0 | — | Quantity scale. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_units_of_measure_e6fb0621`: (code).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``quantity_scale` BETWEEN 0 AND 3`.
- `LENGTH(TRIM(`code`)) > 0`.
- `LENGTH(TRIM(`name`)) > 0`.
- ``quantity_scale` >= 0`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## stock_locations

Module: `Inventory`. Model: [StockLocation](../../app/Modules/Inventory/Models/StockLocation.php).

Source / meaning: BRD p11 rack/location; optional inter-location transfers deferred.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| code | VARCHAR(40) | No | No default | UNIQUE(code) | Code. |
| name | VARCHAR(150) | No | No default | — | Name. |
| description | TEXT | Yes | NULL | — | Description. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_stock_locations_e6fb0621`: (code).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`code`)) > 0`.
- `LENGTH(TRIM(`name`)) > 0`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## spare_parts

Module: `Inventory`. Model: [SparePart](../../app/Modules/Inventory/Models/SparePart.php).

Source / meaning: BRD p11; opening/current quantities derived from stock_movements.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| sku | VARCHAR(80) | No | No default | UNIQUE(sku) | Sku. Immutable through Eloquent after creation. |
| barcode | VARCHAR(100) | Yes | NULL | UNIQUE(barcode) | Barcode. |
| name | VARCHAR(255) | No | No default | — | Name. |
| description | TEXT | Yes | NULL | — | Description. |
| brand_id | BIGINT UNSIGNED | Yes | NULL | FK → spare_part_brands.id; RESTRICT | Brand id. |
| category_id | BIGINT UNSIGNED | Yes | NULL | FK → spare_part_categories.id; RESTRICT | Category id. |
| model_compatibility | TEXT | Yes | NULL | — | Model compatibility. |
| unit_id | BIGINT UNSIGNED | No | No default | FK → units_of_measure.id; RESTRICT | Unit id. |
| selling_price | DECIMAL(19,4) | No | 0 | — | Selling price. CHECK `selling_price` >= 0. |
| minimum_stock_level | DECIMAL(18,3) | No | 0 | — | Minimum stock level. CHECK `minimum_stock_level` >= 0. |
| reorder_level | DECIMAL(18,3) | No | 0 | — | Reorder level. CHECK `reorder_level` >= 0. |
| supplier_id | BIGINT UNSIGNED | Yes | NULL | FK → suppliers.id; RESTRICT | Registered supplier owning this purchase or account event. |
| default_location_id | BIGINT UNSIGNED | Yes | NULL | FK → stock_locations.id; RESTRICT | Default location id. |
| rack | VARCHAR(100) | Yes | NULL | — | Rack. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_spare_parts_cf559ae3`: (sku).
- `uq_spare_parts_006b53f0`: (barcode).
- `ix_spare_parts_6ae99955`: (name).
- `ix_spare_parts_a5d20765`: (brand_id).
- `ix_spare_parts_9c4e4a89`: (category_id).
- `ix_spare_parts_4252c4be`: (unit_id).
- `ix_spare_parts_2bc0d2ca`: (supplier_id).
- `ix_spare_parts_4ea168a9`: (default_location_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`sku`)) > 0`.
- `LENGTH(TRIM(`name`)) > 0`.
- ``selling_price` >= 0`.
- ``minimum_stock_level` >= 0`.
- ``reorder_level` >= 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## document_sequences

Module: `Settings`. Model: [DocumentSequence](../../app/Modules/Settings/Models/DocumentSequence.php).

Source / meaning: Proposed row-locked numbering reservation; no MAX+1 generation.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| scope | VARCHAR(80) | No | No default | UNIQUE(scope) | Scope. |
| prefix | VARCHAR(20) | No | No default | — | Prefix. |
| padding | INTEGER UNSIGNED | No | 5 | — | Padding. |
| next_value | BIGINT UNSIGNED | No | 1 | — | Next value. |
| description | TEXT | Yes | NULL | — | Description. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_document_sequences_aacd3103`: (scope).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``next_value` > 0`.
- ``padding` BETWEEN 1 AND 18`.
- `LENGTH(TRIM(`scope`)) > 0`.
- `LENGTH(TRIM(`prefix`)) > 0`.
- ``padding` >= 0`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## print_templates

Module: `PrintTemplates`. Model: [PrintTemplate](../../app/Modules/PrintTemplates/Models/PrintTemplate.php).

Source / meaning: BRD pp16-17; versioned template settings, no print rendering.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| code | VARCHAR(80) | No | No default | UNIQUE(code,version) | Code. |
| version | INTEGER UNSIGNED | No | 1 | UNIQUE(code,version) | Version. |
| document_type | VARCHAR(50) | No | No default | — | Document type. |
| name | VARCHAR(150) | No | No default | — | Name. |
| business_name | VARCHAR(255) | Yes | NULL | — | Business name. |
| business_address | TEXT | Yes | NULL | — | Business address. |
| telephone_number | VARCHAR(40) | Yes | NULL | — | Telephone number. |
| logo_path | VARCHAR(255) | Yes | NULL | — | Logo path. |
| document_title | VARCHAR(255) | Yes | NULL | — | Document title. |
| paper_size | VARCHAR(64) | No | A5 | — | Paper size. Allowed: A4, A5, A6, thermal. |
| orientation | VARCHAR(64) | No | portrait | — | Orientation. Allowed: portrait, landscape. |
| thermal_width_mm | DECIMAL(19,2) | Yes | NULL | — | Thermal width mm. CHECK `thermal_width_mm` >= 0. |
| font_size_pt | DECIMAL(19,2) | No | 10 | — | Font size pt. CHECK `font_size_pt` >= 0. |
| margin_top_mm | DECIMAL(19,2) | No | 5 | — | Margin top mm. CHECK `margin_top_mm` >= 0. |
| margin_right_mm | DECIMAL(19,2) | No | 5 | — | Margin right mm. CHECK `margin_right_mm` >= 0. |
| margin_bottom_mm | DECIMAL(19,2) | No | 5 | — | Margin bottom mm. CHECK `margin_bottom_mm` >= 0. |
| margin_left_mm | DECIMAL(19,2) | No | 5 | — | Margin left mm. CHECK `margin_left_mm` >= 0. |
| table_widths | JSON | Yes | NULL | — | Table widths. |
| signature_areas | JSON | Yes | NULL | — | Signature areas. |
| footer | TEXT | Yes | NULL | — | Footer. |
| duplicate_copy_label | VARCHAR(100) | No | Duplicate Copy | — | Duplicate copy label. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_print_templates_1f1f8ab9`: (code, version).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``version` > 0`.
- `LENGTH(TRIM(`code`)) > 0`.
- ``version` >= 0`.
- `LENGTH(TRIM(`document_type`)) > 0`.
- `LENGTH(TRIM(`name`)) > 0`.
- ``paper_size` IN ('A4', 'A5', 'A6', 'thermal')`.
- ``orientation` IN ('portrait', 'landscape')`.
- ``thermal_width_mm` >= 0`.
- ``font_size_pt` >= 0`.
- ``margin_top_mm` >= 0`.
- ``margin_right_mm` >= 0`.
- ``margin_bottom_mm` >= 0`.
- ``margin_left_mm` >= 0`.
- ``table_widths` IS NULL OR JSON_VALID(`table_widths`)`.
- ``signature_areas` IS NULL OR JSON_VALID(`signature_areas`)`.
- `LENGTH(TRIM(`duplicate_copy_label`)) > 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## system_settings

Module: `Settings`. Model: [SystemSetting](../../app/Modules/Settings/Models/SystemSetting.php).

Source / meaning: BRD p17; nonsecret settings only, never database/backup credentials.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| group | VARCHAR(60) | No | No default | UNIQUE(group,key) | Group. |
| key | VARCHAR(100) | No | No default | UNIQUE(group,key) | Key. |
| value | JSON | No | No default | — | Value. |
| value_type | VARCHAR(30) | No | string | — | Value type. |
| description | TEXT | Yes | NULL | — | Description. |
| updated_by_user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT | Updated by user id. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_system_settings_3b49c75a`: (group, key).
- `ix_system_settings_7a022eb2`: (updated_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`group`)) > 0`.
- `LENGTH(TRIM(`key`)) > 0`.
- ``value` IS NULL OR JSON_VALID(`value`)`.
- `LENGTH(TRIM(`value_type`)) > 0`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## backup_histories

Module: `BackupRestore`. Model: [BackupHistory](../../app/Modules/BackupRestore/Models/BackupHistory.php).

Source / meaning: BRD p16; references only, secrets remain outside DB.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| backup_at | DATETIME | No | No default | — | Backup at. |
| file_reference | VARCHAR(255) | No | No default | — | File reference. |
| backup_type | VARCHAR(64) | No | manual | — | Backup type. Allowed: manual, scheduled. |
| status | VARCHAR(64) | No | pending | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: pending, running, completed, failed. |
| storage_location | VARCHAR(100) | No | No default | — | Storage location. |
| checksum_sha256 | VARCHAR(64) | Yes | NULL | — | Checksum sha256. |
| verified_at | DATETIME | Yes | NULL | — | Verified at. |
| error_details | TEXT | Yes | NULL | — | Error details. |
| initiated_by_user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT | Initiated by user id. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `ix_backup_histories_5af47fbb`: (backup_at).
- `ix_backup_histories_48a3661d`: (status).
- `ix_backup_histories_5fb88229`: (initiated_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`file_reference`)) > 0`.
- ``backup_type` IN ('manual', 'scheduled')`.
- ``status` IN ('pending', 'running', 'completed', 'failed')`.
- `LENGTH(TRIM(`storage_location`)) > 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## backup_restores

Module: `BackupRestore`. Model: [BackupRestore](../../app/Modules/BackupRestore/Models/BackupRestore.php).

Source / meaning: BRD p16 restore confirmation/verification; proposed attempt history.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| backup_history_id | BIGINT UNSIGNED | No | No default | FK → backup_histories.id; RESTRICT | Backup history id. |
| initiated_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Initiated by user id. |
| confirmed_at | DATETIME | No | No default | — | Confirmed at. |
| started_at | DATETIME | Yes | NULL | — | Started at. |
| completed_at | DATETIME | Yes | NULL | — | Completed at. |
| status | VARCHAR(64) | No | pending | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: pending, verified, running, completed, failed. |
| error_details | TEXT | Yes | NULL | — | Error details. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `ix_backup_restores_b9e34ae4`: (backup_history_id).
- `ix_backup_restores_5fb88229`: (initiated_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``status` IN ('pending', 'verified', 'running', 'completed', 'failed')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## audit_logs

Module: `AuditLogs`. Model: [AuditLog](../../app/Modules/AuditLogs/Models/AuditLog.php).

Source / meaning: BRD pp13,16; polymorphic audit target deliberately retains historical identity.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT | User id. |
| action | VARCHAR(100) | No | No default | — | Action. |
| entity_type | VARCHAR(150) | Yes | NULL | — | Entity type. |
| entity_id | BIGINT UNSIGNED | Yes | NULL | — | Entity id. |
| occurred_at | DATETIME | No | No default | — | Business event timestamp used for date-filtered ledgers, stock history, and audit queries. |
| old_values | JSON | Yes | NULL | — | Old values. |
| new_values | JSON | Yes | NULL | — | New values. |
| ip_address | VARCHAR(45) | Yes | NULL | — | Ip address. |
| request_id | VARCHAR(80) | Yes | NULL | — | Request id. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `ix_audit_logs_da9eab2f`: (entity_type, entity_id).
- `ix_audit_logs_92da6dde`: (action, occurred_at).
- `ix_audit_logs_5fe56ab1`: (user_id, occurred_at).
- `ix_audit_logs_cace4a15`: (user_id).
- `audit_logs_occurred_at_index`: (occurred_at).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`action`)) > 0`.
- ``old_values` IS NULL OR JSON_VALID(`old_values`)`.
- ``new_values` IS NULL OR JSON_VALID(`new_values`)`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## repair_jobs

Module: `RepairJobs`. Model: [RepairJob](../../app/Modules/RepairJobs/Models/RepairJob.php).

Source / meaning: BRD pp5-8; brand/model combined mandatory interpreted as at least one.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,customer_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| job_number | VARCHAR(60) | No | No default | UNIQUE(job_number) | Job number. Immutable through Eloquent after creation. |
| automatic_number | VARCHAR(60) | Yes | NULL | UNIQUE(automatic_number) | Automatic number. Immutable through Eloquent after creation. |
| customer_id | BIGINT UNSIGNED | No | No default | FK → customers.id; RESTRICT; UNIQUE(id,customer_id) | Registered customer owning this transaction; nullable only where anonymous POS is supported. Immutable through Eloquent after creation. |
| item_name | VARCHAR(255) | No | No default | — | Item name. |
| brand | VARCHAR(150) | Yes | NULL | — | Brand. |
| model | VARCHAR(150) | Yes | NULL | — | Model. |
| item_description | TEXT | Yes | NULL | — | Item description. |
| reported_fault | TEXT | Yes | NULL | — | Reported fault. |
| accessories_received | TEXT | Yes | NULL | — | Accessories received. |
| physical_condition | TEXT | Yes | NULL | — | Physical condition. |
| visible_damage | TEXT | Yes | NULL | — | Visible damage. |
| job_type | VARCHAR(64) | No | normal | — | Job type. Allowed: normal, special, urgent. |
| received_at | DATETIME | No | CURRENT_TIMESTAMP | — | Item/goods receipt timestamp; job receiving defaults to the database current timestamp. |
| received_by_employee_id | BIGINT UNSIGNED | No | No default | FK → employees.id; RESTRICT | Registered employee who physically received the item/goods; required where specified by the BRD. |
| technician_id | BIGINT UNSIGNED | Yes | NULL | FK → employees.id; RESTRICT | Technician id. |
| repair_description | TEXT | Yes | NULL | — | Repair description. |
| labour_charge | DECIMAL(19,2) | No | 0 | — | Labour amount charged to the customer; this is a selling charge, not an internal labour cost. CHECK `labour_charge` >= 0. |
| labour_cost | DECIMAL(19,2) | Yes | NULL | — | Known internal labour cost in LKR; NULL means unknown, not zero. Cost attribution policy needs approval. CHECK `labour_cost` >= 0. |
| parts_charge | DECIMAL(19,2) | No | 0 | — | Total selling charge for the job's parts; reconcile to the future approved usage/charge workflow. CHECK `parts_charge` >= 0. |
| parts_cost | DECIMAL(19,2) | Yes | NULL | — | Known internal parts acquisition/consumption cost; NULL remains unknown pending valuation policy. CHECK `parts_cost` >= 0. |
| total_job_amount | DECIMAL(19,2) | No | 0 | — | Job selling amount in LKR; equals labour_charge + parts_charge. Not profit or a payment balance. CHECK `total_job_amount` >= 0. |
| completed_at | DATETIME | Yes | NULL | — | Completed at. |
| delivered_at | DATETIME | Yes | NULL | — | Delivered at. |
| approval_status | VARCHAR(64) | No | pending | — | Approval status. Allowed: pending, approved, rejected. |
| special_notes | TEXT | Yes | NULL | — | Special notes. |
| status | VARCHAR(64) | No | Received | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: Received, Pending Inspection, Under Inspection, Quotation Pending, Waiting for Customer Approval, Customer Contact Failed, Advance Payment Pending, Waiting for Spare Parts, Under Repair, Price Modification, Re – Assembling, Repair Completed, Ready for Collection, Customer Contact Failed After Ready, Delivered, Cancelled, Unable to Repair. |
| cancelled_at | DATETIME | Yes | NULL | — | Cancelled at. |
| cancelled_by_user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT | Cancelled by user id. |
| cancellation_reason | TEXT | Yes | NULL | — | Cancellation reason. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_repair_jobs_a5d450da`: (id, customer_id).
- `uq_repair_jobs_e9c25de9`: (job_number).
- `uq_repair_jobs_8bf12fb6`: (automatic_number).
- `ix_repair_jobs_b8aca1c7`: (status, received_at).
- `ix_repair_jobs_67b6d081`: (technician_id, status).
- `ix_repair_jobs_c6ae1260`: (item_name).
- `ix_repair_jobs_a7a13f4c`: (customer_id).
- `ix_repair_jobs_ec48f282`: (received_by_employee_id).
- `ix_repair_jobs_465640da`: (technician_id).
- `ix_repair_jobs_43e48017`: (cancelled_by_user_id).
- `repair_jobs_received_at_index`: (received_at).
- `repair_jobs_completed_at_index`: (completed_at).
- `repair_jobs_delivered_at_index`: (delivered_at).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(COALESCE(`brand`, ''))) > 0 OR LENGTH(TRIM(COALESCE(`model`, ''))) > 0`.
- `ROUND(`labour_charge` + `parts_charge`, 2) = `total_job_amount``.
- `LENGTH(TRIM(`job_number`)) > 0`.
- `LENGTH(TRIM(`item_name`)) > 0`.
- ``job_type` IN ('normal', 'special', 'urgent')`.
- ``labour_charge` >= 0`.
- ``labour_cost` >= 0`.
- ``parts_charge` >= 0`.
- ``parts_cost` >= 0`.
- ``total_job_amount` >= 0`.
- ``approval_status` IN ('pending', 'approved', 'rejected')`.
- ``status` IN ('Received', 'Pending Inspection', 'Under Inspection', 'Quotation Pending', 'Waiting for Customer Approval', 'Customer Contact Failed', 'Advance Payment Pending', 'Waiting for Spare Parts', 'Under Repair', 'Price Modification', 'Re – Assembling', 'Repair Completed', 'Ready for Collection', 'Customer Contact Failed After Ready', 'Delivered', 'Cancelled', 'Unable to Repair')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## repair_receipts

Module: `RepairReceipts`. Model: [RepairReceipt](../../app/Modules/RepairReceipts/Models/RepairReceipt.php).

Source / meaning: BRD pp5-6; one receipt per job and immutable issued snapshot.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| repair_job_id | BIGINT UNSIGNED | No | No default | FK → repair_jobs.id; RESTRICT; UNIQUE(repair_job_id) | Repair job id. Immutable through Eloquent after creation. |
| customer_id | BIGINT UNSIGNED | No | No default | FK → customers.id; RESTRICT | Registered customer owning this transaction; nullable only where anonymous POS is supported. Immutable through Eloquent after creation. |
| receipt_number | VARCHAR(60) | No | No default | UNIQUE(receipt_number) | Receipt number. Immutable through Eloquent after creation. |
| issued_at | DATETIME | No | CURRENT_TIMESTAMP | — | Issued at. |
| document_snapshot | JSON | Yes | NULL | — | Issued document data/layout snapshot for historically accurate reprints; exclude credentials and unnecessary personal data. Immutable through Eloquent after creation. |
| special_notes | TEXT | Yes | NULL | — | Special notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_repair_receipts_d2dc6852`: (repair_job_id).
- `uq_repair_receipts_dc11faa3`: (receipt_number).
- `fk_repair_receipts_05167f50`: (repair_job_id, customer_id).
- `ix_repair_receipts_a7a13f4c`: (customer_id).

Composite foreign keys:

- (repair_job_id, customer_id) → `repair_jobs` (id, customer_id); RESTRICT delete/update.

Table CHECK rules:

- `LENGTH(TRIM(`receipt_number`)) > 0`.
- ``document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## job_status_histories

Module: `JobTracking`. Model: [JobStatusHistory](../../app/Modules/JobTracking/Models/JobStatusHistory.php).

Source / meaning: BRD pp6-7 exact17statuses and append-only actor/time history.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| repair_job_id | BIGINT UNSIGNED | No | No default | FK → repair_jobs.id; RESTRICT | Repair job id. |
| previous_status | VARCHAR(64) | Yes | NULL | — | Previous status. Allowed: Received, Pending Inspection, Under Inspection, Quotation Pending, Waiting for Customer Approval, Customer Contact Failed, Advance Payment Pending, Waiting for Spare Parts, Under Repair, Price Modification, Re – Assembling, Repair Completed, Ready for Collection, Customer Contact Failed After Ready, Delivered, Cancelled, Unable to Repair. |
| new_status | VARCHAR(64) | No | No default | — | New status. Allowed: Received, Pending Inspection, Under Inspection, Quotation Pending, Waiting for Customer Approval, Customer Contact Failed, Advance Payment Pending, Waiting for Spare Parts, Under Repair, Price Modification, Re – Assembling, Repair Completed, Ready for Collection, Customer Contact Failed After Ready, Delivered, Cancelled, Unable to Repair. |
| changed_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Changed by user id. |
| changed_at | DATETIME | No | No default | — | Changed at. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `ix_job_status_histories_04832136`: (repair_job_id, changed_at).
- `ix_job_status_histories_ee56f6a4`: (new_status, changed_at).
- `ix_job_status_histories_d2dc6852`: (repair_job_id).
- `ix_job_status_histories_69259fd8`: (changed_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``previous_status` IN ('Received', 'Pending Inspection', 'Under Inspection', 'Quotation Pending', 'Waiting for Customer Approval', 'Customer Contact Failed', 'Advance Payment Pending', 'Waiting for Spare Parts', 'Under Repair', 'Price Modification', 'Re – Assembling', 'Repair Completed', 'Ready for Collection', 'Customer Contact Failed After Ready', 'Delivered', 'Cancelled', 'Unable to Repair')`.
- ``new_status` IN ('Received', 'Pending Inspection', 'Under Inspection', 'Quotation Pending', 'Waiting for Customer Approval', 'Customer Contact Failed', 'Advance Payment Pending', 'Waiting for Spare Parts', 'Under Repair', 'Price Modification', 'Re – Assembling', 'Repair Completed', 'Ready for Collection', 'Customer Contact Failed After Ready', 'Delivered', 'Cancelled', 'Unable to Repair')`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## job_inspections

Module: `RepairJobs`. Model: [JobInspection](../../app/Modules/RepairJobs/Models/JobInspection.php).

Source / meaning: BRD pp6-7,18; multiple inspections retained.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| repair_job_id | BIGINT UNSIGNED | No | No default | FK → repair_jobs.id; RESTRICT | Repair job id. |
| inspected_at | DATETIME | No | No default | — | Inspected at. |
| inspected_by_employee_id | BIGINT UNSIGNED | No | No default | FK → employees.id; RESTRICT | Inspected by employee id. |
| existing_fault | TEXT | Yes | NULL | — | Existing fault. |
| inspection_result | TEXT | Yes | NULL | — | Inspection result. |
| technical_information | TEXT | Yes | NULL | — | Technical information. |
| work_performed | TEXT | Yes | NULL | — | Work performed. |
| repaired_by_employee_id | BIGINT UNSIGNED | Yes | NULL | FK → employees.id; RESTRICT | Repaired by employee id. |
| repair_completed_at | DATETIME | Yes | NULL | — | Repair completed at. |
| testing_details | TEXT | Yes | NULL | — | Testing details. |
| special_notes | TEXT | Yes | NULL | — | Special notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `ix_job_inspections_d2dc6852`: (repair_job_id).
- `ix_job_inspections_4a26244b`: (inspected_by_employee_id).
- `ix_job_inspections_9909258e`: (repaired_by_employee_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:


Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## job_cards

Module: `JobCards`. Model: [JobCard](../../app/Modules/JobCards/Models/JobCard.php).

Source / meaning: BRD p7; normalized job-card identity, no duplicate repair fields.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| repair_job_id | BIGINT UNSIGNED | No | No default | FK → repair_jobs.id; RESTRICT; UNIQUE(repair_job_id) | Repair job id. |
| print_template_id | BIGINT UNSIGNED | Yes | NULL | FK → print_templates.id; RESTRICT | Print template id. |
| paper_size | VARCHAR(10) | No | A6 | — | Paper size. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_job_cards_d2dc6852`: (repair_job_id).
- `ix_job_cards_52a2214d`: (print_template_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`paper_size`)) > 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## job_approvals

Module: `RepairJobs`. Model: [JobApproval](../../app/Modules/RepairJobs/Models/JobApproval.php).

Source / meaning: BRD pp6,8 customer repair approval; proposed append-only decision evidence.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| repair_job_id | BIGINT UNSIGNED | No | No default | FK → repair_jobs.id; RESTRICT | Repair job id. |
| decision | VARCHAR(64) | No | No default | — | Decision. Allowed: pending, approved, rejected. |
| decided_at | DATETIME | No | No default | — | Decided at. |
| approved_by_name | VARCHAR(255) | Yes | NULL | — | Approved by name. |
| recorded_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Recorded by user id. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `ix_job_approvals_d2dc6852`: (repair_job_id).
- `ix_job_approvals_06129e94`: (recorded_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``decision` IN ('pending', 'approved', 'rejected')`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## quotations

Module: `Quotations`. Model: [Quotation](../../app/Modules/Quotations/Models/Quotation.php).

Source / meaning: BRD pp7-8; revisions have unique document numbers and approval histories.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,repair_job_id,customer_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| quotation_number | VARCHAR(60) | No | No default | UNIQUE(quotation_number) | Quotation number. Immutable through Eloquent after creation. |
| revision | INTEGER UNSIGNED | No | 1 | — | Revision. |
| revises_quotation_id | BIGINT UNSIGNED | Yes | NULL | FK → quotations.id; RESTRICT; UNIQUE(revises_quotation_id) | Revises quotation id. Immutable through Eloquent after creation. |
| quotation_date | DATE | No | No default | — | Quotation date. |
| repair_job_id | BIGINT UNSIGNED | No | No default | FK → repair_jobs.id; RESTRICT; UNIQUE(id,repair_job_id,customer_id) | Repair job id. Immutable through Eloquent after creation. |
| customer_id | BIGINT UNSIGNED | No | No default | FK → customers.id; RESTRICT; UNIQUE(id,repair_job_id,customer_id) | Registered customer owning this transaction; nullable only where anonymous POS is supported. Immutable through Eloquent after creation. |
| proposed_repair_description | TEXT | No | No default | — | Proposed repair description. |
| document_snapshot | JSON | Yes | NULL | — | Issued document data/layout snapshot for historically accurate reprints; exclude credentials and unnecessary personal data. |
| labour_charge | DECIMAL(19,2) | No | 0 | — | Labour amount charged to the customer; this is a selling charge, not an internal labour cost. CHECK `labour_charge` >= 0. |
| parts_total | DECIMAL(19,2) | No | 0 | — | Sum of rounded part-line estimates. |
| subtotal | DECIMAL(19,2) | No | 0 | — | Historical document subtotal in LKR before document discount; reconcile to its source lines at finalization. CHECK `subtotal` >= 0. |
| discount_amount | DECIMAL(19,2) | No | 0 | — | Nonnegative discount amount in LKR. Line or document checks prevent a discount larger than its gross value. CHECK `discount_amount` >= 0. |
| total | DECIMAL(19,2) | No | 0 | — | Historical document total in LKR; header CHECK enforces subtotal less discount plus explicit other costs where present. CHECK `total` >= 0. |
| valid_until | DATE | Yes | NULL | — | Valid until. |
| terms_and_conditions | TEXT | Yes | NULL | — | Terms and conditions. |
| approval_status | VARCHAR(64) | No | pending | — | Approval status. Allowed: pending, approved, rejected. |
| status | VARCHAR(64) | No | draft | — | Legacy document status, retained for schema compatibility: draft, finalized, cancelled. Workflow preparation status is stored separately. |
| preparation_status | VARCHAR(32) | No | draft | — | Quotation preparation state: draft, issued, superseded, cancelled. |
| issued_at | DATETIME | Yes | NULL | — | Time the customer-facing quotation was issued and its snapshot frozen. |
| created_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Created by user id. |
| updated_by_user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT | User responsible for the latest mutable draft update or issue. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_quotations_4bfa8930`: (id, repair_job_id, customer_id).
- `uq_quotations_73fda6a0`: (quotation_number).
- `uq_quotations_b0fb8224`: (revises_quotation_id).
- `fk_quotations_05167f50`: (repair_job_id, customer_id).
- `fk_quotations_69de8f01`: (revises_quotation_id, repair_job_id, customer_id).
- `ix_quotations_0bd5d101`: (quotation_date).
- `ix_quotations_d2dc6852`: (repair_job_id).
- `ix_quotations_a7a13f4c`: (customer_id).
- `ix_quotations_ae7720e8`: (created_by_user_id).
- `ix_quotations_prep_approval`: (preparation_status, approval_status).
- `quotations_updated_by_user_id_foreign`: (updated_by_user_id), supporting the foreign key.

Composite foreign keys:

- (repair_job_id, customer_id) → `repair_jobs` (id, customer_id); RESTRICT delete/update.
- (revises_quotation_id, repair_job_id, customer_id) → `quotations` (id, repair_job_id, customer_id); RESTRICT delete/update.
- `updated_by_user_id` → `users.id`; RESTRICT delete/update.

Table CHECK rules:

- ``revision` > 0`.
- ``discount_amount` <= `subtotal``.
- `ROUND(`subtotal` - `discount_amount`, 2) = `total``.
- ``valid_until` IS NULL OR `valid_until` >= `quotation_date``.
- `LENGTH(TRIM(`quotation_number`)) > 0`.
- ``revision` >= 0`.
- ``document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)`.
- ``labour_charge` >= 0`.
- ``subtotal` >= 0`.
- ``discount_amount` >= 0`.
- ``total` >= 0`.
- ``approval_status` IN ('pending', 'approved', 'rejected')`.
- ``status` IN ('draft', 'finalized', 'cancelled')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## quotation_items

Module: `Quotations`. Model: [QuotationItem](../../app/Modules/Quotations/Models/QuotationItem.php).

Source / meaning: BRD p7 proposed repair/parts/labour lines.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| quotation_id | BIGINT UNSIGNED | No | No default | FK → quotations.id; RESTRICT; UNIQUE(quotation_id,line_number) | Quotation id. |
| line_number | INTEGER UNSIGNED | No | No default | UNIQUE(quotation_id,line_number) | Line number. |
| display_order | INTEGER UNSIGNED | No | 0 | — | Current display order of active quotation lines. |
| item_type | VARCHAR(64) | No | part | — | Item type. Allowed: part, labour, service. |
| spare_part_id | BIGINT UNSIGNED | Yes | NULL | FK → spare_parts.id; RESTRICT | Spare part id. |
| description | TEXT | No | No default | — | Description. |
| quantity | DECIMAL(18,3) | No | 1 | — | Positive source-line quantity in its spare part unit. DECIMAL capacity does not approve fractional units; validate against unit.quantity_scale later. CHECK `quantity` > 0. |
| unit_price | DECIMAL(19,4) | No | 0 | — | Historical unit selling price, four decimals; preserve for later reprints and reconciliation. CHECK `unit_price` >= 0. |
| discount_amount | DECIMAL(19,2) | No | 0 | — | Nonnegative discount amount in LKR. Line or document checks prevent a discount larger than its gross value. CHECK `discount_amount` >= 0. |
| line_total | DECIMAL(19,2) | No | 0 | — | Net line amount: proposed ROUND(quantity * rate - line discount, 2); rounding policy requires business confirmation CHECK `line_total` >= 0. |
| removed_at | DATETIME | Yes | NULL | — | Removed draft line timestamp; retained instead of deleting the line. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_quotation_items_9d5c5c2c`: (quotation_id, line_number).
- `ix_quotation_items_596c572d`: (quotation_id).
- `ix_quotation_items_10e3c1ce`: (spare_part_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``line_number` > 0`.
- ``discount_amount` <= `quantity` * `unit_price``.
- `ROUND(`quantity` * `unit_price` - `discount_amount`, 2) = `line_total``.
- ``line_number` >= 0`.
- ``item_type` IN ('part', 'labour', 'service')`.
- ``quantity` > 0`.
- ``unit_price` >= 0`.
- ``discount_amount` >= 0`.
- ``line_total` >= 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## quotation_approvals

Module: `Quotations`. Model: [QuotationApproval](../../app/Modules/Quotations/Models/QuotationApproval.php).

Source / meaning: BRD p8 approval/rejection and revision-specific history.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| quotation_id | BIGINT UNSIGNED | No | No default | FK → quotations.id; RESTRICT | Quotation id. |
| decision | VARCHAR(64) | No | No default | — | Decision. Allowed: pending, approved, rejected. |
| decided_at | DATETIME | No | No default | — | Decided at. |
| customer_contact_name | VARCHAR(255) | Yes | NULL | — | Customer contact name. |
| approval_method | VARCHAR(32) | Yes | NULL | — | Customer communication method: in_person, telephone, email, or other. |
| recorded_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Recorded by user id. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `ix_quotation_approvals_596c572d`: (quotation_id).
- `ix_quotation_approvals_06129e94`: (recorded_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``decision` IN ('pending', 'approved', 'rejected')`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## quotation_history

Module: `Quotations`. Model: [QuotationHistory](../../app/Modules/Quotations/Models/QuotationHistory.php).

Source / meaning: Step 6 append-only workflow event and before/after summaries.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated history identifier. |
| quotation_id | BIGINT UNSIGNED | No | No default | FK → quotations.id; RESTRICT | Quotation whose event is recorded. |
| actor_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | User performing the event. |
| event_type | VARCHAR(50) | No | No default | — | Event name, including creation, edits, issue, supersession, cancellation, revision, and customer decision. |
| old_values | JSON | Yes | NULL | — | Redacted business-state summary before the event. |
| new_values | JSON | Yes | NULL | — | Redacted business-state summary after the event. |
| event_at | DATETIME | No | No default | — | Business event timestamp. |
| created_at | DATETIME | Yes | NULL | — | ORM row creation timestamp. |
| updated_at | DATETIME | Yes | NULL | — | ORM row update timestamp; Eloquent model is append-only. |

Indexes:

- `PRIMARY`: (id).
- `quotation_history_quotation_id_foreign`: (quotation_id), supporting the foreign key.
- `quotation_history_actor_user_id_foreign`: (actor_user_id), supporting the foreign key.
- `ix_quotation_history_event`: (quotation_id, event_at).
- `ix_quotation_history_actor`: (actor_user_id).

Record policy: append-only via Eloquent; corrections create new evidence. Raw SQL and eventless operations bypass model event guards.

## purchases

Module: `Purchases`. Model: [Purchase](../../app/Modules/Purchases/Models/Purchase.php).

Source / meaning: BRD p12; purchase drafts cause no stock movement.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,supplier_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| purchase_number | VARCHAR(60) | No | No default | UNIQUE(purchase_number) | Purchase number. Immutable through Eloquent after creation. |
| supplier_id | BIGINT UNSIGNED | No | No default | FK → suppliers.id; RESTRICT; UNIQUE(supplier_id,supplier_invoice_number); UNIQUE(id,supplier_id) | Registered supplier owning this purchase or account event. Immutable through Eloquent after creation. |
| supplier_invoice_number | VARCHAR(100) | Yes | NULL | UNIQUE(supplier_id,supplier_invoice_number) | Supplier invoice number. |
| purchase_date | DATE | No | No default | — | Purchase date. |
| subtotal | DECIMAL(19,2) | No | 0 | — | Historical document subtotal in LKR before document discount; reconcile to its source lines at finalization. CHECK `subtotal` >= 0. |
| discount_amount | DECIMAL(19,2) | No | 0 | — | Nonnegative discount amount in LKR. Line or document checks prevent a discount larger than its gross value. CHECK `discount_amount` >= 0. |
| other_costs | DECIMAL(19,2) | No | 0 | — | Other costs. CHECK `other_costs` >= 0. |
| total | DECIMAL(19,2) | No | 0 | — | Historical document total in LKR; header CHECK enforces subtotal less discount plus explicit other costs where present. CHECK `total` >= 0. |
| payment_terms | VARCHAR(64) | No | cash | — | Payment terms. Allowed: cash, credit. |
| payment_method | VARCHAR(64) | Yes | NULL | — | Informational tender method selected for the invoice; Step 7 does not record a receipt or payment. |
| due_date | DATE | Yes | NULL | — | Due date. |
| status | VARCHAR(64) | No | draft | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: draft, finalized, cancelled. |
| created_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Created by user id. |
| finalized_at | DATETIME | Yes | NULL | — | Finalized at. |
| cancelled_at | DATETIME | Yes | NULL | — | Cancelled at. |
| cancellation_reason | TEXT | Yes | NULL | — | Cancellation reason. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_purchases_4e0db050`: (id, supplier_id).
- `uq_purchases_6c59959c`: (purchase_number).
- `uq_purchases_95419c78`: (supplier_id, supplier_invoice_number).
- `ix_purchases_d9987142`: (purchase_date).
- `ix_purchases_2bc0d2ca`: (supplier_id).
- `ix_purchases_ae7720e8`: (created_by_user_id).
- `purchases_status_due_date_index`: (status, due_date).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``discount_amount` <= `subtotal``.
- `ROUND(`subtotal` - `discount_amount` + `other_costs`, 2) = `total``.
- `LENGTH(TRIM(`purchase_number`)) > 0`.
- ``subtotal` >= 0`.
- ``discount_amount` >= 0`.
- ``other_costs` >= 0`.
- ``total` >= 0`.
- ``payment_terms` IN ('cash', 'credit')`.
- ``status` IN ('draft', 'finalized', 'cancelled')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## purchase_items

Module: `Purchases`. Model: [PurchaseItem](../../app/Modules/Purchases/Models/PurchaseItem.php).

Source / meaning: BRD p12 quantity and acquisition cost.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,purchase_id); UNIQUE(id,spare_part_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| purchase_id | BIGINT UNSIGNED | No | No default | FK → purchases.id; RESTRICT; UNIQUE(purchase_id,line_number); UNIQUE(id,purchase_id) | Purchase id. |
| line_number | INTEGER UNSIGNED | No | No default | UNIQUE(purchase_id,line_number) | Line number. |
| spare_part_id | BIGINT UNSIGNED | No | No default | FK → spare_parts.id; RESTRICT; UNIQUE(id,spare_part_id) | Spare part id. |
| description | TEXT | No | No default | — | Description. |
| quantity | DECIMAL(18,3) | No | 0 | — | Positive source-line quantity in its spare part unit. DECIMAL capacity does not approve fractional units; validate against unit.quantity_scale later. CHECK `quantity` > 0. |
| unit_cost | DECIMAL(19,4) | No | 0 | — | Historical known acquisition/consumption unit cost, four decimals. NULL, if allowed, means unknown; no FIFO/average valuation policy is selected. CHECK `unit_cost` >= 0. |
| discount_amount | DECIMAL(19,2) | No | 0 | — | Nonnegative discount amount in LKR. Line or document checks prevent a discount larger than its gross value. CHECK `discount_amount` >= 0. |
| line_total | DECIMAL(19,2) | No | 0 | — | Net line amount: proposed ROUND(quantity * rate - line discount, 2); rounding policy requires business confirmation CHECK `line_total` >= 0. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_purchase_items_ed918c72`: (purchase_id, line_number).
- `uq_purchase_items_951d6c0e`: (id, purchase_id).
- `uq_purchase_items_26e56352`: (id, spare_part_id).
- `ix_purchase_items_86cc2ebe`: (purchase_id).
- `ix_purchase_items_10e3c1ce`: (spare_part_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``line_number` > 0`.
- ``discount_amount` <= `quantity` * `unit_cost``.
- `ROUND(`quantity` * `unit_cost` - `discount_amount`, 2) = `line_total``.
- ``line_number` >= 0`.
- ``quantity` > 0`.
- ``unit_cost` >= 0`.
- ``discount_amount` >= 0`.
- ``line_total` >= 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## goods_received_notes

Module: `Purchases`. Model: [GoodsReceivedNote](../../app/Modules/Purchases/Models/GoodsReceivedNote.php).

Source / meaning: BRD p12; future finalized-GRN transaction posts stock.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,purchase_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| grn_number | VARCHAR(60) | No | No default | UNIQUE(grn_number) | Grn number. Immutable through Eloquent after creation. |
| purchase_id | BIGINT UNSIGNED | No | No default | FK → purchases.id; RESTRICT; UNIQUE(id,purchase_id) | Purchase id. Immutable through Eloquent after creation. |
| supplier_id | BIGINT UNSIGNED | No | No default | FK → suppliers.id; RESTRICT | Registered supplier owning this purchase or account event. Immutable through Eloquent after creation. |
| received_at | DATETIME | No | No default | — | Item/goods receipt timestamp; job receiving defaults to the database current timestamp. |
| received_by_employee_id | BIGINT UNSIGNED | No | No default | FK → employees.id; RESTRICT | Registered employee who physically received the item/goods; required where specified by the BRD. |
| status | VARCHAR(64) | No | draft | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: draft, finalized, cancelled. |
| finalized_at | DATETIME | Yes | NULL | — | Finalized at. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_goods_received_notes_951d6c0e`: (id, purchase_id).
- `uq_goods_received_notes_da00b996`: (grn_number).
- `fk_goods_received_notes_3e1bac6a`: (purchase_id, supplier_id).
- `ix_goods_received_notes_86cc2ebe`: (purchase_id).
- `ix_goods_received_notes_2bc0d2ca`: (supplier_id).
- `ix_goods_received_notes_ec48f282`: (received_by_employee_id).

Composite foreign keys:

- (purchase_id, supplier_id) → `purchases` (id, supplier_id); RESTRICT delete/update.

Table CHECK rules:

- `LENGTH(TRIM(`grn_number`)) > 0`.
- ``status` IN ('draft', 'finalized', 'cancelled')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## goods_received_note_items

Module: `Purchases`. Model: [GoodsReceivedNoteItem](../../app/Modules/Purchases/Models/GoodsReceivedNoteItem.php).

Source / meaning: BRD p12; partial receiving without multiplying draft stock.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,spare_part_id,location_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| goods_received_note_id | BIGINT UNSIGNED | No | No default | FK → goods_received_notes.id; RESTRICT; UNIQUE(goods_received_note_id,purchase_item_id,location_id) | Goods received note id. |
| purchase_id | BIGINT UNSIGNED | No | No default | FK → purchases.id; RESTRICT | Purchase id. |
| purchase_item_id | BIGINT UNSIGNED | No | No default | FK → purchase_items.id; RESTRICT; UNIQUE(goods_received_note_id,purchase_item_id,location_id) | Purchase item id. |
| spare_part_id | BIGINT UNSIGNED | No | No default | FK → spare_parts.id; RESTRICT; UNIQUE(id,spare_part_id,location_id) | Spare part id. |
| location_id | BIGINT UNSIGNED | No | No default | FK → stock_locations.id; RESTRICT; UNIQUE(goods_received_note_id,purchase_item_id,location_id); UNIQUE(id,spare_part_id,location_id) | Location id. |
| quantity | DECIMAL(18,3) | No | 0 | — | Positive source-line quantity in its spare part unit. DECIMAL capacity does not approve fractional units; validate against unit.quantity_scale later. CHECK `quantity` > 0. |
| unit_cost | DECIMAL(19,4) | No | 0 | — | Historical known acquisition/consumption unit cost, four decimals. NULL, if allowed, means unknown; no FIFO/average valuation policy is selected. CHECK `unit_cost` >= 0. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_goods_received_note_items_156a1229`: (goods_received_note_id, purchase_item_id, location_id).
- `uq_goods_received_note_items_d9f67aa6`: (id, spare_part_id, location_id).
- `fk_goods_received_note_items_f23d6f0e`: (goods_received_note_id, purchase_id).
- `fk_goods_received_note_items_d8bea874`: (purchase_item_id, purchase_id).
- `fk_goods_received_note_items_25d8e711`: (purchase_item_id, spare_part_id).
- `ix_goods_received_note_items_fc342424`: (goods_received_note_id).
- `ix_goods_received_note_items_86cc2ebe`: (purchase_id).
- `ix_goods_received_note_items_e7e6cd38`: (purchase_item_id).
- `ix_goods_received_note_items_10e3c1ce`: (spare_part_id).
- `ix_goods_received_note_items_876d7bdb`: (location_id).

Composite foreign keys:

- (goods_received_note_id, purchase_id) → `goods_received_notes` (id, purchase_id); RESTRICT delete/update.
- (purchase_item_id, purchase_id) → `purchase_items` (id, purchase_id); RESTRICT delete/update.
- (purchase_item_id, spare_part_id) → `purchase_items` (id, spare_part_id); RESTRICT delete/update.

Table CHECK rules:

- ``quantity` > 0`.
- ``unit_cost` >= 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## invoices

Module: `Invoices`. Model: [Invoice](../../app/Modules/Invoices/Models/Invoice.php).

Source / meaning: BRD pp8-10; payment status/outstanding derived separately from credit terms.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,customer_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| invoice_number | VARCHAR(60) | No | No default | UNIQUE(invoice_number) | Invoice number. Immutable through Eloquent after creation. |
| invoice_date | DATE | No | No default | — | Invoice date. |
| customer_id | BIGINT UNSIGNED | No | No default | FK → customers.id; RESTRICT; UNIQUE(id,customer_id) | Registered customer owning this transaction; nullable only where anonymous POS is supported. Immutable through Eloquent after creation. |
| subtotal | DECIMAL(19,2) | No | 0 | — | Historical document subtotal in LKR before document discount; reconcile to its source lines at finalization. CHECK `subtotal` >= 0. |
| discount_amount | DECIMAL(19,2) | No | 0 | — | Nonnegative discount amount in LKR. Line or document checks prevent a discount larger than its gross value. CHECK `discount_amount` >= 0. |
| total | DECIMAL(19,2) | No | 0 | — | Historical document total in LKR; header CHECK enforces subtotal less discount plus explicit other costs where present. CHECK `total` >= 0. |
| payment_terms | VARCHAR(64) | No | cash | — | Payment terms. Allowed: cash, credit. |
| due_date | DATE | Yes | NULL | — | Due date. |
| status | VARCHAR(64) | No | draft | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: draft, finalized, cancelled. |
| cashier_employee_id | BIGINT UNSIGNED | No | No default | FK → employees.id; RESTRICT | Registered employee responsible for this sale/invoice. |
| collected_by | VARCHAR(255) | Yes | NULL | — | Collected by. |
| vehicle_number | VARCHAR(40) | Yes | NULL | — | Vehicle number. |
| delivery_at | DATETIME | Yes | NULL | — | Delivery at. |
| delivery_information | TEXT | Yes | NULL | — | Delivery information. |
| special_notes | TEXT | Yes | NULL | — | Special notes. |
| document_snapshot | JSON | Yes | NULL | — | Issued document data/layout snapshot for historically accurate reprints; exclude credentials and unnecessary personal data. |
| finalized_at | DATETIME | Yes | NULL | — | Finalized at. |
| cancelled_at | DATETIME | Yes | NULL | — | Cancelled at. |
| cancelled_by_user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT | Cancelled by user id. |
| cancellation_reason | TEXT | Yes | NULL | — | Cancellation reason. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_invoices_a5d450da`: (id, customer_id).
- `uq_invoices_60284980`: (invoice_number).
- `ix_invoices_c0000d83`: (invoice_date).
- `ix_invoices_feb4944b`: (status, invoice_date).
- `ix_invoices_a7a13f4c`: (customer_id).
- `ix_invoices_782ecea6`: (cashier_employee_id).
- `ix_invoices_43e48017`: (cancelled_by_user_id).
- `invoices_status_due_date_index`: (status, due_date).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``discount_amount` <= `subtotal``.
- `ROUND(`subtotal` - `discount_amount`, 2) = `total``.
- `LENGTH(TRIM(`invoice_number`)) > 0`.
- ``subtotal` >= 0`.
- ``discount_amount` >= 0`.
- ``total` >= 0`.
- ``payment_terms` IN ('cash', 'credit')`.
- ``status` IN ('draft', 'finalized', 'cancelled')`.
- ``document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## invoice_jobs

Module: `Invoices`. Model: [InvoiceJob](../../app/Modules/Invoices/Models/InvoiceJob.php).

Source / meaning: BRD p9 same-customer multi-job invoice; conservative one invoice reservation per job.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,invoice_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| invoice_id | BIGINT UNSIGNED | No | No default | FK → invoices.id; RESTRICT; UNIQUE(invoice_id,repair_job_id); UNIQUE(id,invoice_id) | Invoice id. Immutable through Eloquent after creation. |
| repair_job_id | BIGINT UNSIGNED | No | No default | FK → repair_jobs.id; RESTRICT; UNIQUE(invoice_id,repair_job_id); UNIQUE(repair_job_id) | Repair job id. Immutable through Eloquent after creation. |
| customer_id | BIGINT UNSIGNED | No | No default | FK → customers.id; RESTRICT | Registered customer owning this transaction; nullable only where anonymous POS is supported. Immutable through Eloquent after creation. |
| job_amount | DECIMAL(19,2) | No | 0 | — | This job's individual billed amount within the same-customer invoice. CHECK `job_amount` >= 0. |
| job_snapshot | JSON | Yes | NULL | — | Historical job facts and billed description as issued on this invoice. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_invoice_jobs_393bb4c9`: (invoice_id, repair_job_id).
- `uq_invoice_jobs_ec756383`: (id, invoice_id).
- `uq_invoice_jobs_d2dc6852`: (repair_job_id).
- `fk_invoice_jobs_62d0b35a`: (invoice_id, customer_id).
- `fk_invoice_jobs_05167f50`: (repair_job_id, customer_id).
- `ix_invoice_jobs_15d25c20`: (invoice_id).
- `ix_invoice_jobs_a7a13f4c`: (customer_id).

Composite foreign keys:

- (invoice_id, customer_id) → `invoices` (id, customer_id); RESTRICT delete/update.
- (repair_job_id, customer_id) → `repair_jobs` (id, customer_id); RESTRICT delete/update.

Table CHECK rules:

- ``job_amount` >= 0`.
- ``job_snapshot` IS NULL OR JSON_VALID(`job_snapshot`)`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## invoice_items

Module: `Invoices`. Model: [InvoiceItem](../../app/Modules/Invoices/Models/InvoiceItem.php).

Source / meaning: BRD pp9,14; billed lines and known costs distinct from selling prices.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| invoice_id | BIGINT UNSIGNED | No | No default | FK → invoices.id; RESTRICT; UNIQUE(invoice_id,line_number) | Invoice id. |
| invoice_job_id | BIGINT UNSIGNED | No | No default | FK → invoice_jobs.id; RESTRICT | Invoice job id. |
| line_number | INTEGER UNSIGNED | No | No default | UNIQUE(invoice_id,line_number) | Line number. |
| quotation_item_id | BIGINT UNSIGNED | Yes | NULL | FK → quotation_items.id; RESTRICT | Quotation item id. |
| item_type | VARCHAR(64) | No | service | — | Item type. Allowed: part, labour, service. |
| spare_part_id | BIGINT UNSIGNED | Yes | NULL | FK → spare_parts.id; RESTRICT | Spare part id. |
| description | TEXT | No | No default | — | Description. |
| quantity | DECIMAL(18,3) | No | 1 | — | Positive source-line quantity in its spare part unit. DECIMAL capacity does not approve fractional units; validate against unit.quantity_scale later. CHECK `quantity` > 0. |
| unit_price | DECIMAL(19,4) | No | 0 | — | Historical unit selling price, four decimals; preserve for later reprints and reconciliation. CHECK `unit_price` >= 0. |
| unit_cost | DECIMAL(19,4) | Yes | NULL | — | Historical known acquisition/consumption unit cost, four decimals. NULL, if allowed, means unknown; no FIFO/average valuation policy is selected. CHECK `unit_cost` >= 0. |
| discount_amount | DECIMAL(19,2) | No | 0 | — | Nonnegative discount amount in LKR. Line or document checks prevent a discount larger than its gross value. CHECK `discount_amount` >= 0. |
| line_total | DECIMAL(19,2) | No | 0 | — | Net line amount: proposed ROUND(quantity * rate - line discount, 2); rounding policy requires business confirmation CHECK `line_total` >= 0. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_invoice_items_d0e58f0e`: (invoice_id, line_number).
- `fk_invoice_items_cf7ebf0c`: (invoice_job_id, invoice_id).
- `ix_invoice_items_15d25c20`: (invoice_id).
- `ix_invoice_items_0d3839f6`: (invoice_job_id).
- `ix_invoice_items_c7f10065`: (quotation_item_id).
- `ix_invoice_items_10e3c1ce`: (spare_part_id).

Composite foreign keys:

- (invoice_job_id, invoice_id) → `invoice_jobs` (id, invoice_id); RESTRICT delete/update.

Table CHECK rules:

- ``discount_amount` <= `quantity` * `unit_price``.
- `ROUND(`quantity` * `unit_price` - `discount_amount`, 2) = `line_total``.
- ``line_number` >= 0`.
- ``item_type` IN ('part', 'labour', 'service')`.
- ``quantity` > 0`.
- ``unit_price` >= 0`.
- ``unit_cost` >= 0`.
- ``discount_amount` >= 0`.
- ``line_total` >= 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## invoice_history

Module: `Invoices`. Model: [InvoiceHistory](../../app/Modules/Invoices/Models/InvoiceHistory.php).

Source / meaning: BRD pp. 9,16; append-only invoice lifecycle, edit and job-link history.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier. |
| invoice_id | BIGINT UNSIGNED | No | No default | FK → invoices.id; RESTRICT | Invoice whose event is recorded. |
| actor_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Authenticated actor responsible for the event. |
| event_type | VARCHAR(50) | No | No default | — | Event such as `draft_created`, `draft_updated`, `job_linked`, `issued`, or `cancelled`. |
| old_values | JSON | Yes | NULL | — | Relevant before-values, when applicable. |
| new_values | JSON | Yes | NULL | — | Relevant after-values, when applicable. |
| event_at | DATETIME | No | No default | — | Business event time. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp. |

Indexes:

- `PRIMARY`: (id).
- `ix_invoice_history_event`: (invoice_id, event_at).
- `ix_invoice_history_actor`: (actor_user_id).

Table CHECK rules:

- No explicit CHECK constraints; MySQL/MariaDB JSON columns validate JSON values natively.

Record policy: append-only through Eloquent; corrections create subsequent evidence. Raw SQL and eventless operations bypass model event guards.

## pos_sales

Module: `POS`. Model: [PosSale](../../app/Modules/POS/Models/PosSale.php).

Source / meaning: BRD pp3,10-11,13-19; tender methods normalized through payments.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,customer_id); UNIQUE(id,customer_scope_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| sale_number | VARCHAR(60) | No | No default | UNIQUE(sale_number) | Sale number. Immutable through Eloquent after creation. |
| sale_at | DATETIME | No | No default | — | Sale at. |
| customer_id | BIGINT UNSIGNED | Yes | NULL | FK → customers.id; RESTRICT; UNIQUE(id,customer_id) | Registered customer owning this transaction; nullable only where anonymous POS is supported. Immutable through Eloquent after creation. |
| cashier_employee_id | BIGINT UNSIGNED | No | No default | FK → employees.id; RESTRICT | Registered employee responsible for this sale/invoice. |
| subtotal | DECIMAL(19,2) | No | 0 | — | Historical document subtotal in LKR before document discount; reconcile to its source lines at finalization. CHECK `subtotal` >= 0. |
| discount_amount | DECIMAL(19,2) | No | 0 | — | Nonnegative discount amount in LKR. Line or document checks prevent a discount larger than its gross value. CHECK `discount_amount` >= 0. |
| total | DECIMAL(19,2) | No | 0 | — | Historical document total in LKR; header CHECK enforces subtotal less discount plus explicit other costs where present. CHECK `total` >= 0. |
| payment_terms | VARCHAR(64) | No | cash | — | Payment terms. Allowed: cash, credit. |
| payment_method | VARCHAR(64) | Yes | NULL | UNIQUE(payment_method,payment_reference) | POS tender method: cash, bank_transfer, or cheque. |
| payment_status | VARCHAR(64) | No | pending | — | POS-specific payment lifecycle: pending, settled, partially_refunded, refunded. |
| payment_reference | VARCHAR(100) | Yes | NULL | UNIQUE(payment_method,payment_reference) | Bank/cheque reference; unique within payment method. |
| cheque_date | DATE | Yes | NULL | — | Cheque date supplied at checkout; does not mean the cheque has cleared. |
| bank_name | VARCHAR(150) | Yes | NULL | — | Bank name supplied for transfer/cheque records. |
| amount_tendered | DECIMAL(19,2) | No | 0 | — | Cash tender received; payment ledger records sale total, not this tender amount. |
| change_due | DECIMAL(19,2) | No | 0 | — | Change returned to customer, derived from tender less sale total. |
| idempotency_key | VARCHAR(100) | Yes | NULL | UNIQUE(idempotency_key) | Stable checkout retry key. |
| idempotency_hash | VARCHAR(64) | Yes | NULL | — | SHA-256 fingerprint of the normalized checkout request and cart; rejects reuse of a key with changed data. |
| payment_verified_by_user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT | Manager who confirmed receipt of a pending transfer/cheque. |
| payment_verified_at | DATETIME | Yes | NULL | — | Time pending tender was confirmed and the sale was finalized. |
| status | VARCHAR(64) | No | draft | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: draft, finalized, cancelled. |
| document_snapshot | JSON | Yes | NULL | — | Issued document data/layout snapshot for historically accurate reprints; exclude credentials and unnecessary personal data. |
| finalized_at | DATETIME | Yes | NULL | — | Finalized at. |
| cancelled_at | DATETIME | Yes | NULL | — | Cancelled at. |
| cancellation_reason | TEXT | Yes | NULL | — | Cancellation reason. |
| notes | TEXT | Yes | NULL | — | Notes. |
| customer_scope_id | BIGINT UNSIGNED | No | 0 | UNIQUE(id,customer_scope_id) | Checked owner scope: customer ID, or 0 only for anonymous POS; not a second balance |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_pos_sales_ad3551cc`: (id, customer_scope_id).
- `uq_pos_sales_1407226b`: (sale_number).
- `uq_pos_sales_a5d450da`: (id, customer_id).
- `uq_step10_pos_payment_reference`: (payment_method, payment_reference).
- `uq_step10_pos_sale_idempotency`: (idempotency_key).
- `ix_pos_sales_a8fee2cf`: (sale_at).
- `ix_pos_sales_a7a13f4c`: (customer_id).
- `ix_pos_sales_782ecea6`: (cashier_employee_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``discount_amount` <= `subtotal``.
- `ROUND(`subtotal` - `discount_amount`, 2) = `total``.
- ``payment_terms` <> 'credit' OR `customer_id` IS NOT NULL`.
- ``customer_scope_id` = COALESCE(`customer_id`, 0)`.
- `LENGTH(TRIM(`sale_number`)) > 0`.
- ``subtotal` >= 0`.
- ``discount_amount` >= 0`.
- ``total` >= 0`.
- ``payment_terms` IN ('cash', 'credit')`.
- ``status` IN ('draft', 'finalized', 'cancelled')`.
- ``document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## pos_sale_items

Module: `POS`. Model: [PosSaleItem](../../app/Modules/POS/Models/PosSaleItem.php).

Source / meaning: BRD p11 shared POS stock; cost snapshot is not approved valuation policy.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,spare_part_id,location_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| pos_sale_id | BIGINT UNSIGNED | No | No default | FK → pos_sales.id; RESTRICT; UNIQUE(pos_sale_id,line_number) | Pos sale id. |
| line_number | INTEGER UNSIGNED | No | No default | UNIQUE(pos_sale_id,line_number) | Line number. |
| spare_part_id | BIGINT UNSIGNED | No | No default | FK → spare_parts.id; RESTRICT; UNIQUE(id,spare_part_id,location_id) | Spare part id. |
| location_id | BIGINT UNSIGNED | No | No default | FK → stock_locations.id; RESTRICT; UNIQUE(id,spare_part_id,location_id) | Location id. |
| description | TEXT | No | No default | — | Description. |
| quantity | DECIMAL(18,3) | No | 0 | — | Positive source-line quantity in its spare part unit. DECIMAL capacity does not approve fractional units; validate against unit.quantity_scale later. CHECK `quantity` > 0. |
| unit_price | DECIMAL(19,4) | No | 0 | — | Historical unit selling price, four decimals; preserve for later reprints and reconciliation. CHECK `unit_price` >= 0. |
| unit_cost | DECIMAL(19,4) | Yes | NULL | — | Historical known acquisition/consumption unit cost, four decimals. NULL, if allowed, means unknown; no FIFO/average valuation policy is selected. CHECK `unit_cost` >= 0. |
| discount_amount | DECIMAL(19,2) | No | 0 | — | Nonnegative discount amount in LKR. Line or document checks prevent a discount larger than its gross value. CHECK `discount_amount` >= 0. |
| line_total | DECIMAL(19,2) | No | 0 | — | Net line amount: proposed ROUND(quantity * rate - line discount, 2); rounding policy requires business confirmation CHECK `line_total` >= 0. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_pos_sale_items_3da73ab0`: (pos_sale_id, line_number).
- `uq_pos_sale_items_d9f67aa6`: (id, spare_part_id, location_id).
- `ix_pos_sale_items_bc1ec442`: (pos_sale_id).
- `ix_pos_sale_items_10e3c1ce`: (spare_part_id).
- `ix_pos_sale_items_876d7bdb`: (location_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``discount_amount` <= `quantity` * `unit_price``.
- `ROUND(`quantity` * `unit_price` - `discount_amount`, 2) = `line_total``.
- ``line_number` >= 0`.
- ``quantity` > 0`.
- ``unit_price` >= 0`.
- ``unit_cost` >= 0`.
- ``discount_amount` >= 0`.
- ``line_total` >= 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## payments

Module: `Payments`. Model: [Payment](../../app/Modules/Payments/Models/Payment.php).

Source / meaning: BRD p10; each receipt of money recorded once regardless of reallocation.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,customer_id); UNIQUE(id,customer_scope_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| payment_number | VARCHAR(60) | No | No default | UNIQUE(payment_number) | Payment number. Immutable through Eloquent after creation. |
| customer_id | BIGINT UNSIGNED | Yes | NULL | FK → customers.id; RESTRICT; UNIQUE(id,customer_id) | Registered customer owning this transaction; nullable only where anonymous POS is supported. |
| payment_type | VARCHAR(64) | No | invoice | — | Payment type. Allowed: advance, invoice, credit_collection, pos. |
| method | VARCHAR(64) | No | No default | — | Method. Allowed: cash, bank_transfer, cheque. |
| amount | DECIMAL(19,2) | No | 0 | — | Amount. CHECK `amount` > 0. |
| paid_at | DATETIME | No | No default | — | Paid at. |
| received_by_employee_id | BIGINT UNSIGNED | No | No default | FK → employees.id; RESTRICT | Registered employee who physically received the item/goods; required where specified by the BRD. |
| bank_reference | VARCHAR(100) | Yes | NULL | — | Bank reference. |
| cheque_number | VARCHAR(100) | Yes | NULL | — | Cheque number. |
| cheque_date | DATE | Yes | NULL | — | Cheque date. |
| bank_name | VARCHAR(150) | Yes | NULL | — | Bank name. |
| notes | TEXT | Yes | NULL | — | Notes. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| customer_scope_id | BIGINT UNSIGNED | No | 0 | UNIQUE(id,customer_scope_id) | Checked owner scope: customer ID, or 0 only for anonymous POS; not a second balance |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_payments_ad3551cc`: (id, customer_scope_id).
- `uq_payments_39743c6d`: (payment_number).
- `uq_payments_7db4ecce`: (idempotency_key).
- `uq_payments_a5d450da`: (id, customer_id).
- `ix_payments_7c3ebe1b`: (paid_at).
- `ix_payments_6b26a8f9`: (method, paid_at).
- `ix_payments_a7a13f4c`: (customer_id).
- `ix_payments_ec48f282`: (received_by_employee_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``payment_type` = 'pos' OR `customer_id` IS NOT NULL`.
- ``customer_scope_id` = COALESCE(`customer_id`, 0)`.
- `LENGTH(TRIM(`payment_number`)) > 0`.
- ``payment_type` IN ('advance', 'invoice', 'credit_collection', 'pos')`.
- ``method` IN ('cash', 'bank_transfer', 'cheque')`.
- ``amount` > 0`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## payment_allocations

Module: `Payments`. Model: [PaymentAllocation](../../app/Modules/Payments/Models/PaymentAllocation.php).

Source / meaning: BRD p10; allocation is application, not another payment or sale.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,payment_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| payment_id | BIGINT UNSIGNED | No | No default | FK → payments.id; RESTRICT; UNIQUE(id,payment_id) | Payment id. |
| customer_id | BIGINT UNSIGNED | Yes | NULL | FK → customers.id; RESTRICT | Registered customer owning this transaction; nullable only where anonymous POS is supported. |
| target_type | VARCHAR(64) | No | No default | — | Target type. Allowed: job_advance, invoice, pos_sale, opening_balance. |
| repair_job_id | BIGINT UNSIGNED | Yes | NULL | FK → repair_jobs.id; RESTRICT | Repair job id. |
| invoice_id | BIGINT UNSIGNED | Yes | NULL | FK → invoices.id; RESTRICT | Invoice id. |
| pos_sale_id | BIGINT UNSIGNED | Yes | NULL | FK → pos_sales.id; RESTRICT | Pos sale id. |
| amount | DECIMAL(19,2) | No | 0 | — | Amount. CHECK `amount` > 0. |
| allocated_at | DATETIME | No | No default | — | Allocated at. |
| allocated_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Allocated by user id. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| customer_scope_id | BIGINT UNSIGNED | No | 0 | — | Checked owner scope: customer ID, or 0 only for anonymous POS; not a second balance |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_payment_allocations_ae6c2db1`: (id, payment_id).
- `uq_payment_allocations_7db4ecce`: (idempotency_key).
- `fk_payment_allocations_5bcc665a`: (payment_id, customer_id).
- `fk_payment_allocations_05167f50`: (repair_job_id, customer_id).
- `fk_payment_allocations_62d0b35a`: (invoice_id, customer_id).
- `fk_payment_allocations_4355babb`: (pos_sale_id, customer_id).
- `fk_payment_allocations_5f5d56da`: (payment_id, customer_scope_id).
- `fk_payment_allocations_e4f5269a`: (pos_sale_id, customer_scope_id).
- `ix_payment_allocations_fcaec7df`: (payment_id).
- `ix_payment_allocations_a7a13f4c`: (customer_id).
- `ix_payment_allocations_d2dc6852`: (repair_job_id).
- `ix_payment_allocations_15d25c20`: (invoice_id).
- `ix_payment_allocations_bc1ec442`: (pos_sale_id).
- `ix_payment_allocations_997c1d72`: (allocated_by_user_id).

Composite foreign keys:

- (payment_id, customer_id) → `payments` (id, customer_id); RESTRICT delete/update.
- (repair_job_id, customer_id) → `repair_jobs` (id, customer_id); RESTRICT delete/update.
- (invoice_id, customer_id) → `invoices` (id, customer_id); RESTRICT delete/update.
- (pos_sale_id, customer_id) → `pos_sales` (id, customer_id); RESTRICT delete/update.
- (payment_id, customer_scope_id) → `payments` (id, customer_scope_id); RESTRICT delete/update.
- (pos_sale_id, customer_scope_id) → `pos_sales` (id, customer_scope_id); RESTRICT delete/update.

Table CHECK rules:

- `(`target_type` = 'job_advance' AND `repair_job_id` IS NOT NULL AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `customer_id` IS NOT NULL) OR (`target_type` = 'invoice' AND `invoice_id` IS NOT NULL AND `repair_job_id` IS NULL AND `pos_sale_id` IS NULL AND `customer_id` IS NOT NULL) OR (`target_type` = 'pos_sale' AND `pos_sale_id` IS NOT NULL AND `repair_job_id` IS NULL AND `invoice_id` IS NULL) OR (`target_type` = 'opening_balance' AND `customer_id` IS NOT NULL AND `repair_job_id` IS NULL AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL)`.
- ``customer_scope_id` = COALESCE(`customer_id`, 0)`.
- ``target_type` IN ('job_advance', 'invoice', 'pos_sale', 'opening_balance')`.
- ``amount` > 0`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## payment_reversals

Module: `Payments`. Model: [PaymentReversal](../../app/Modules/Payments/Models/PaymentReversal.php).

Source / meaning: BRD p10; original payment retained; aggregate caps require future transaction service.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,payment_id); UNIQUE(id,customer_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| payment_id | BIGINT UNSIGNED | No | No default | FK → payments.id; RESTRICT; UNIQUE(id,payment_id) | Payment id. |
| customer_id | BIGINT UNSIGNED | Yes | NULL | FK → customers.id; RESTRICT; UNIQUE(id,customer_id) | Registered customer owning this transaction; nullable only where anonymous POS is supported. |
| reversal_type | VARCHAR(64) | No | No default | — | Reversal type. Allowed: refund, reversal. |
| amount | DECIMAL(19,2) | No | 0 | — | Amount. CHECK `amount` > 0. |
| method | VARCHAR(64) | Yes | NULL | — | Method. Allowed: cash, bank_transfer, cheque. |
| reversed_at | DATETIME | No | No default | — | Reversed at. |
| reversed_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Reversed by user id. |
| reason | TEXT | No | No default | — | Reason. |
| external_reference | VARCHAR(100) | Yes | NULL | — | External reference. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| customer_scope_id | BIGINT UNSIGNED | No | 0 | — | Checked owner scope: customer ID, or 0 only for anonymous POS; not a second balance |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_payment_reversals_ae6c2db1`: (id, payment_id).
- `uq_payment_reversals_7db4ecce`: (idempotency_key).
- `uq_payment_reversals_a5d450da`: (id, customer_id).
- `fk_payment_reversals_5f5d56da`: (payment_id, customer_scope_id).
- `ix_payment_reversals_fcaec7df`: (payment_id).
- `ix_payment_reversals_a7a13f4c`: (customer_id).
- `ix_payment_reversals_07fdd642`: (reversed_by_user_id).

Composite foreign keys:

- (payment_id, customer_scope_id) → `payments` (id, customer_scope_id); RESTRICT delete/update.

Table CHECK rules:

- ``customer_scope_id` = COALESCE(`customer_id`, 0)`.
- ``reversal_type` IN ('refund', 'reversal')`.
- ``amount` > 0`.
- ``method` IN ('cash', 'bank_transfer', 'cheque')`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## payment_allocation_releases

Module: `Payments`. Model: [PaymentAllocationRelease](../../app/Modules/Payments/Models/PaymentAllocationRelease.php).

Source / meaning: Proposed append-only release for atomic advance-to-invoice reapplication.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| payment_allocation_id | BIGINT UNSIGNED | No | No default | FK → payment_allocations.id; RESTRICT | Payment allocation id. |
| payment_id | BIGINT UNSIGNED | No | No default | FK → payments.id; RESTRICT | Payment id. |
| payment_reversal_id | BIGINT UNSIGNED | Yes | NULL | FK → payment_reversals.id; RESTRICT | Payment reversal id. |
| amount | DECIMAL(19,2) | No | 0 | — | Amount. CHECK `amount` > 0. |
| reason_type | VARCHAR(64) | No | No default | — | Reason type. Allowed: reapplication, refund, reversal. |
| released_at | DATETIME | No | No default | — | Released at. |
| released_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Released by user id. |
| notes | TEXT | Yes | NULL | — | Notes. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_payment_allocation_releases_7db4ecce`: (idempotency_key).
- `fk_payment_allocation_releases_cb606553`: (payment_allocation_id, payment_id).
- `fk_payment_allocation_releases_b1bc50fb`: (payment_reversal_id, payment_id).
- `ix_payment_allocation_releases_c33ff68d`: (payment_allocation_id).
- `ix_payment_allocation_releases_fcaec7df`: (payment_id).
- `ix_payment_allocation_releases_5b312e0c`: (payment_reversal_id).
- `ix_payment_allocation_releases_e78980db`: (released_by_user_id).

Composite foreign keys:

- (payment_allocation_id, payment_id) → `payment_allocations` (id, payment_id); RESTRICT delete/update.
- (payment_reversal_id, payment_id) → `payment_reversals` (id, payment_id); RESTRICT delete/update.

Table CHECK rules:

- ``amount` > 0`.
- ``reason_type` IN ('reapplication', 'refund', 'reversal')`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## supplier_payments

Module: `Suppliers`. Model: [SupplierPayment](../../app/Modules/Suppliers/Models/SupplierPayment.php).

Source / meaning: BRD p12 supplier payment history and partial settlement.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,supplier_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| payment_number | VARCHAR(60) | No | No default | UNIQUE(payment_number) | Payment number. Immutable through Eloquent after creation. |
| supplier_id | BIGINT UNSIGNED | No | No default | FK → suppliers.id; RESTRICT; UNIQUE(id,supplier_id) | Registered supplier owning this purchase or account event. |
| method | VARCHAR(64) | No | No default | — | Method. Allowed: cash, bank_transfer, cheque. |
| amount | DECIMAL(19,2) | No | 0 | — | Amount. CHECK `amount` > 0. |
| paid_at | DATETIME | No | No default | — | Paid at. |
| paid_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Paid by user id. |
| bank_reference | VARCHAR(100) | Yes | NULL | — | Bank reference. |
| cheque_number | VARCHAR(100) | Yes | NULL | — | Cheque number. |
| cheque_date | DATE | Yes | NULL | — | Cheque date. |
| notes | TEXT | Yes | NULL | — | Notes. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_supplier_payments_4e0db050`: (id, supplier_id).
- `uq_supplier_payments_39743c6d`: (payment_number).
- `uq_supplier_payments_7db4ecce`: (idempotency_key).
- `ix_supplier_payments_7c3ebe1b`: (paid_at).
- `ix_supplier_payments_2bc0d2ca`: (supplier_id).
- `ix_supplier_payments_38e2e5db`: (paid_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`payment_number`)) > 0`.
- ``method` IN ('cash', 'bank_transfer', 'cheque')`.
- ``amount` > 0`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## supplier_payment_allocations

Module: `Suppliers`. Model: [SupplierPaymentAllocation](../../app/Modules/Suppliers/Models/SupplierPaymentAllocation.php).

Source / meaning: BRD p12; normalized supplier payment applications.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,supplier_payment_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| supplier_payment_id | BIGINT UNSIGNED | No | No default | FK → supplier_payments.id; RESTRICT; UNIQUE(id,supplier_payment_id) | Supplier payment id. |
| supplier_id | BIGINT UNSIGNED | No | No default | FK → suppliers.id; RESTRICT | Registered supplier owning this purchase or account event. |
| purchase_id | BIGINT UNSIGNED | Yes | NULL | FK → purchases.id; RESTRICT | Purchase id. |
| target_type | VARCHAR(64) | No | purchase | — | Target type. Allowed: purchase, opening_balance. |
| amount | DECIMAL(19,2) | No | 0 | — | Amount. CHECK `amount` > 0. |
| allocated_at | DATETIME | No | No default | — | Allocated at. |
| allocated_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Allocated by user id. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_supplier_payment_allocations_3f75acb8`: (id, supplier_payment_id).
- `uq_supplier_payment_allocations_7db4ecce`: (idempotency_key).
- `fk_supplier_payment_allocations_e24bcc51`: (supplier_payment_id, supplier_id).
- `fk_supplier_payment_allocations_3e1bac6a`: (purchase_id, supplier_id).
- `ix_supplier_payment_allocations_69c70222`: (supplier_payment_id).
- `ix_supplier_payment_allocations_2bc0d2ca`: (supplier_id).
- `ix_supplier_payment_allocations_86cc2ebe`: (purchase_id).
- `ix_supplier_payment_allocations_997c1d72`: (allocated_by_user_id).

Composite foreign keys:

- (supplier_payment_id, supplier_id) → `supplier_payments` (id, supplier_id); RESTRICT delete/update.
- (purchase_id, supplier_id) → `purchases` (id, supplier_id); RESTRICT delete/update.

Table CHECK rules:

- `(`target_type` = 'purchase' AND `purchase_id` IS NOT NULL) OR (`target_type` = 'opening_balance' AND `purchase_id` IS NULL)`.
- ``target_type` IN ('purchase', 'opening_balance')`.
- ``amount` > 0`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## supplier_payment_reversals

Module: `Suppliers`. Model: [SupplierPaymentReversal](../../app/Modules/Suppliers/Models/SupplierPaymentReversal.php).

Source / meaning: Step2 sec13 supplier reversals; proposed auditable storage.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,supplier_payment_id); UNIQUE(id,supplier_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| supplier_payment_id | BIGINT UNSIGNED | No | No default | FK → supplier_payments.id; RESTRICT; UNIQUE(id,supplier_payment_id) | Supplier payment id. |
| supplier_id | BIGINT UNSIGNED | No | No default | FK → suppliers.id; RESTRICT; UNIQUE(id,supplier_id) | Registered supplier owning this purchase or account event. |
| reversal_type | VARCHAR(64) | No | No default | — | Reversal type. Allowed: refund, reversal. |
| amount | DECIMAL(19,2) | No | 0 | — | Amount. CHECK `amount` > 0. |
| reversed_at | DATETIME | No | No default | — | Reversed at. |
| reversed_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Reversed by user id. |
| reason | TEXT | No | No default | — | Reason. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_supplier_payment_reversals_3f75acb8`: (id, supplier_payment_id).
- `uq_supplier_payment_reversals_4e0db050`: (id, supplier_id).
- `uq_supplier_payment_reversals_7db4ecce`: (idempotency_key).
- `fk_supplier_payment_reversals_e24bcc51`: (supplier_payment_id, supplier_id).
- `ix_supplier_payment_reversals_69c70222`: (supplier_payment_id).
- `ix_supplier_payment_reversals_2bc0d2ca`: (supplier_id).
- `ix_supplier_payment_reversals_07fdd642`: (reversed_by_user_id).

Composite foreign keys:

- (supplier_payment_id, supplier_id) → `supplier_payments` (id, supplier_id); RESTRICT delete/update.

Table CHECK rules:

- ``reversal_type` IN ('refund', 'reversal')`.
- ``amount` > 0`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## supplier_allocation_releases

Module: `Suppliers`. Model: [SupplierAllocationRelease](../../app/Modules/Suppliers/Models/SupplierAllocationRelease.php).

Source / meaning: Proposed allocation corrections without overwriting supplier payments.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| supplier_payment_allocation_id | BIGINT UNSIGNED | No | No default | FK → supplier_payment_allocations.id; RESTRICT | Supplier payment allocation id. |
| supplier_payment_id | BIGINT UNSIGNED | No | No default | FK → supplier_payments.id; RESTRICT | Supplier payment id. |
| supplier_payment_reversal_id | BIGINT UNSIGNED | Yes | NULL | FK → supplier_payment_reversals.id; RESTRICT | Supplier payment reversal id. |
| amount | DECIMAL(19,2) | No | 0 | — | Amount. CHECK `amount` > 0. |
| released_at | DATETIME | No | No default | — | Released at. |
| released_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Released by user id. |
| reason | TEXT | No | No default | — | Reason. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_supplier_allocation_releases_7db4ecce`: (idempotency_key).
- `fk_supplier_allocation_releases_fd2690eb`: (supplier_payment_allocation_id, supplier_payment_id).
- `fk_supplier_allocation_releases_2e689253`: (supplier_payment_reversal_id, supplier_payment_id).
- `ix_supplier_allocation_releases_ba299898`: (supplier_payment_allocation_id).
- `ix_supplier_allocation_releases_69c70222`: (supplier_payment_id).
- `ix_supplier_allocation_releases_3ec7caab`: (supplier_payment_reversal_id).
- `ix_supplier_allocation_releases_e78980db`: (released_by_user_id).

Composite foreign keys:

- (supplier_payment_allocation_id, supplier_payment_id) → `supplier_payment_allocations` (id, supplier_payment_id); RESTRICT delete/update.
- (supplier_payment_reversal_id, supplier_payment_id) → `supplier_payment_reversals` (id, supplier_payment_id); RESTRICT delete/update.

Table CHECK rules:

- ``amount` > 0`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## customer_account_entries

Module: `CreditManagement`. Model: [CustomerAccountEntry](../../app/Modules/CreditManagement/Models/CustomerAccountEntry.php).

Source / meaning: BRD pp4,10,12,14; proposed canonical signed account ledger.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,customer_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| customer_id | BIGINT UNSIGNED | No | No default | FK → customers.id; RESTRICT; UNIQUE(id,customer_id) | Registered customer owning this transaction; nullable only where anonymous POS is supported. |
| entry_type | VARCHAR(64) | No | No default | — | Entry type. Allowed: opening, invoice, pos_sale, payment, refund, reversal, adjustment. |
| amount | DECIMAL(19,2) | No | 0 | — | Signed canonical account delta: positive increases receivable/payable; negative decreases it |
| occurred_at | DATETIME | No | No default | — | Business event timestamp used for date-filtered ledgers, stock history, and audit queries. |
| reference_number | VARCHAR(80) | No | No default | — | Human-readable source reference retained with the immutable financial or stock event. |
| description | TEXT | No | No default | — | Description. |
| recorded_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Recorded by user id. |
| invoice_id | BIGINT UNSIGNED | Yes | NULL | FK → invoices.id; RESTRICT; UNIQUE(invoice_id) | Invoice id. |
| pos_sale_id | BIGINT UNSIGNED | Yes | NULL | FK → pos_sales.id; RESTRICT; UNIQUE(pos_sale_id) | Pos sale id. |
| payment_id | BIGINT UNSIGNED | Yes | NULL | FK → payments.id; RESTRICT; UNIQUE(payment_id) | Payment id. |
| payment_reversal_id | BIGINT UNSIGNED | Yes | NULL | FK → payment_reversals.id; RESTRICT; UNIQUE(payment_reversal_id) | Payment reversal id. |
| reverses_entry_id | BIGINT UNSIGNED | Yes | NULL | FK → customer_account_entries.id; RESTRICT; UNIQUE(reverses_entry_id) | Original account posting being fully compensated. Unique reference permits one full ledger reversal; inverse amount requires future validation. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| opening_owner_id | BIGINT UNSIGNED | Yes | GENERATED: CASE WHEN `entry_type` = 'opening' THEN `customer_id` ELSE NULL END | UNIQUE(opening_owner_id) | Generated opening marker; unique permits one opening entry per account |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_customer_account_entries_a5d450da`: (id, customer_id).
- `uq_customer_account_entries_7db4ecce`: (idempotency_key).
- `uq_customer_account_entries_1de3c56e`: (opening_owner_id).
- `uq_customer_account_entries_15d25c20`: (invoice_id).
- `uq_customer_account_entries_bc1ec442`: (pos_sale_id).
- `uq_customer_account_entries_fcaec7df`: (payment_id).
- `uq_customer_account_entries_5b312e0c`: (payment_reversal_id).
- `uq_customer_account_entries_fc740855`: (reverses_entry_id).
- `fk_customer_account_entries_5585485a`: (payment_reversal_id, customer_id).
- `fk_customer_account_entries_62d0b35a`: (invoice_id, customer_id).
- `fk_customer_account_entries_4355babb`: (pos_sale_id, customer_id).
- `fk_customer_account_entries_5bcc665a`: (payment_id, customer_id).
- `fk_customer_account_entries_afce3f4c`: (reverses_entry_id, customer_id).
- `ix_customer_account_entries_d947b0a0`: (customer_id, occurred_at).
- `ix_customer_account_entries_a7a13f4c`: (customer_id).
- `ix_customer_account_entries_06129e94`: (recorded_by_user_id).

Composite foreign keys:

- (payment_reversal_id, customer_id) → `payment_reversals` (id, customer_id); RESTRICT delete/update.
- (invoice_id, customer_id) → `invoices` (id, customer_id); RESTRICT delete/update.
- (pos_sale_id, customer_id) → `pos_sales` (id, customer_id); RESTRICT delete/update.
- (payment_id, customer_id) → `payments` (id, customer_id); RESTRICT delete/update.
- (reverses_entry_id, customer_id) → `customer_account_entries` (id, customer_id); RESTRICT delete/update.

Table CHECK rules:

- ``amount` <> 0`.
- `(`entry_type` IN ('invoice', 'pos_sale', 'refund') AND `amount` > 0) OR (`entry_type` = 'payment' AND `amount` < 0) OR (`entry_type` IN ('opening', 'adjustment', 'reversal'))`.
- ``entry_type` IN ('opening', 'invoice', 'pos_sale', 'payment', 'refund', 'reversal', 'adjustment')`.
- `LENGTH(TRIM(`reference_number`)) > 0`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.
- `(`entry_type` = 'invoice' AND `invoice_id` IS NOT NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'pos_sale' AND `invoice_id` IS NULL AND `pos_sale_id` IS NOT NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'payment' AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NOT NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'refund' AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NOT NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'reversal' AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NOT NULL) OR (`entry_type` IN ('opening','adjustment') AND `invoice_id` IS NULL AND `pos_sale_id` IS NULL AND `payment_id` IS NULL AND `payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL)`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## supplier_account_entries

Module: `Suppliers`. Model: [SupplierAccountEntry](../../app/Modules/Suppliers/Models/SupplierAccountEntry.php).

Source / meaning: BRD pp4,10,12,14; proposed canonical signed account ledger.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,supplier_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| supplier_id | BIGINT UNSIGNED | No | No default | FK → suppliers.id; RESTRICT; UNIQUE(id,supplier_id) | Registered supplier owning this purchase or account event. |
| entry_type | VARCHAR(64) | No | No default | — | Entry type. Allowed: opening, purchase, payment, refund, reversal, adjustment. |
| amount | DECIMAL(19,2) | No | 0 | — | Signed canonical account delta: positive increases receivable/payable; negative decreases it |
| occurred_at | DATETIME | No | No default | — | Business event timestamp used for date-filtered ledgers, stock history, and audit queries. |
| reference_number | VARCHAR(80) | No | No default | — | Human-readable source reference retained with the immutable financial or stock event. |
| description | TEXT | No | No default | — | Description. |
| recorded_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Recorded by user id. |
| purchase_id | BIGINT UNSIGNED | Yes | NULL | FK → purchases.id; RESTRICT; UNIQUE(purchase_id) | Purchase id. |
| supplier_payment_id | BIGINT UNSIGNED | Yes | NULL | FK → supplier_payments.id; RESTRICT; UNIQUE(supplier_payment_id) | Supplier payment id. |
| supplier_payment_reversal_id | BIGINT UNSIGNED | Yes | NULL | FK → supplier_payment_reversals.id; RESTRICT; UNIQUE(supplier_payment_reversal_id) | Supplier payment reversal id. |
| reverses_entry_id | BIGINT UNSIGNED | Yes | NULL | FK → supplier_account_entries.id; RESTRICT; UNIQUE(reverses_entry_id) | Original account posting being fully compensated. Unique reference permits one full ledger reversal; inverse amount requires future validation. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| opening_owner_id | BIGINT UNSIGNED | Yes | GENERATED: CASE WHEN `entry_type` = 'opening' THEN `supplier_id` ELSE NULL END | UNIQUE(opening_owner_id) | Generated opening marker; unique permits one opening entry per account |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_supplier_account_entries_4e0db050`: (id, supplier_id).
- `uq_supplier_account_entries_7db4ecce`: (idempotency_key).
- `uq_supplier_account_entries_1de3c56e`: (opening_owner_id).
- `uq_supplier_account_entries_86cc2ebe`: (purchase_id).
- `uq_supplier_account_entries_69c70222`: (supplier_payment_id).
- `uq_supplier_account_entries_3ec7caab`: (supplier_payment_reversal_id).
- `uq_supplier_account_entries_fc740855`: (reverses_entry_id).
- `fk_supplier_account_entries_10afd45a`: (supplier_payment_reversal_id, supplier_id).
- `fk_supplier_account_entries_3e1bac6a`: (purchase_id, supplier_id).
- `fk_supplier_account_entries_e24bcc51`: (supplier_payment_id, supplier_id).
- `fk_supplier_account_entries_80141e8c`: (reverses_entry_id, supplier_id).
- `ix_supplier_account_entries_a3fe3b0b`: (supplier_id, occurred_at).
- `ix_supplier_account_entries_2bc0d2ca`: (supplier_id).
- `ix_supplier_account_entries_06129e94`: (recorded_by_user_id).

Composite foreign keys:

- (supplier_payment_reversal_id, supplier_id) → `supplier_payment_reversals` (id, supplier_id); RESTRICT delete/update.
- (purchase_id, supplier_id) → `purchases` (id, supplier_id); RESTRICT delete/update.
- (supplier_payment_id, supplier_id) → `supplier_payments` (id, supplier_id); RESTRICT delete/update.
- (reverses_entry_id, supplier_id) → `supplier_account_entries` (id, supplier_id); RESTRICT delete/update.

Table CHECK rules:

- ``amount` <> 0`.
- `(`entry_type` IN ('purchase', 'refund') AND `amount` > 0) OR (`entry_type` = 'payment' AND `amount` < 0) OR (`entry_type` IN ('opening', 'adjustment', 'reversal'))`.
- ``entry_type` IN ('opening', 'purchase', 'payment', 'refund', 'reversal', 'adjustment')`.
- `LENGTH(TRIM(`reference_number`)) > 0`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.
- `(`entry_type` = 'purchase' AND `purchase_id` IS NOT NULL AND `supplier_payment_id` IS NULL AND `supplier_payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'payment' AND `purchase_id` IS NULL AND `supplier_payment_id` IS NOT NULL AND `supplier_payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'refund' AND `purchase_id` IS NULL AND `supplier_payment_id` IS NULL AND `supplier_payment_reversal_id` IS NOT NULL AND `reverses_entry_id` IS NULL) OR (`entry_type` = 'reversal' AND `purchase_id` IS NULL AND `supplier_payment_id` IS NULL AND `supplier_payment_reversal_id` IS NULL AND `reverses_entry_id` IS NOT NULL) OR (`entry_type` IN ('opening','adjustment') AND `purchase_id` IS NULL AND `supplier_payment_id` IS NULL AND `supplier_payment_reversal_id` IS NULL AND `reverses_entry_id` IS NULL)`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## job_part_usages

Module: `Inventory`. Model: [JobPartUsage](../../app/Modules/Inventory/Models/JobPartUsage.php).

Source / meaning: BRD pp6,11 approved/issued repair parts; unique issue source in stock ledger.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,spare_part_id,location_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| repair_job_id | BIGINT UNSIGNED | No | No default | FK → repair_jobs.id; RESTRICT | Repair job id. |
| spare_part_id | BIGINT UNSIGNED | No | No default | FK → spare_parts.id; RESTRICT; UNIQUE(id,spare_part_id,location_id) | Spare part id. |
| location_id | BIGINT UNSIGNED | No | No default | FK → stock_locations.id; RESTRICT; UNIQUE(id,spare_part_id,location_id) | Location id. |
| quantity | DECIMAL(18,3) | No | 0 | — | Positive source-line quantity in its spare part unit. DECIMAL capacity does not approve fractional units; validate against unit.quantity_scale later. CHECK `quantity` > 0. |
| unit_cost | DECIMAL(19,4) | Yes | NULL | — | Historical known acquisition/consumption unit cost, four decimals. NULL, if allowed, means unknown; no FIFO/average valuation policy is selected. CHECK `unit_cost` >= 0. |
| unit_price | DECIMAL(19,4) | No | 0 | — | Historical unit selling price, four decimals; preserve for later reprints and reconciliation. CHECK `unit_price` >= 0. |
| status | VARCHAR(64) | No | draft | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: draft, approved, issued, cancelled. |
| requested_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Requested by user id. |
| approved_at | DATETIME | Yes | NULL | — | Approved at. |
| approved_by_user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT | Approved by user id. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_job_part_usages_d9f67aa6`: (id, spare_part_id, location_id).
- `uq_job_part_usages_7db4ecce`: (idempotency_key).
- `ix_job_part_usages_d2dc6852`: (repair_job_id).
- `ix_job_part_usages_10e3c1ce`: (spare_part_id).
- `ix_job_part_usages_876d7bdb`: (location_id).
- `ix_job_part_usages_1d9e5646`: (requested_by_user_id).
- `ix_job_part_usages_46df84f0`: (approved_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``quantity` > 0`.
- ``unit_cost` >= 0`.
- ``unit_price` >= 0`.
- ``status` IN ('draft', 'approved', 'issued', 'cancelled')`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## stock_adjustments

Module: `Inventory`. Model: [StockAdjustment](../../app/Modules/Inventory/Models/StockAdjustment.php).

Source / meaning: BRD p11 adjustment/damaged stock with reason and actor.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| adjustment_number | VARCHAR(60) | No | No default | UNIQUE(adjustment_number) | Adjustment number. Immutable through Eloquent after creation. |
| adjusted_at | DATETIME | No | No default | — | Adjusted at. |
| adjustment_type | VARCHAR(64) | No | correction | — | Adjustment type. Allowed: correction, damaged. |
| reason | TEXT | No | No default | — | Reason. |
| created_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Created by user id. |
| status | VARCHAR(64) | No | draft | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: draft, finalized, cancelled. |
| approved_at | DATETIME | Yes | NULL | — | Approved at. |
| approved_by_user_id | BIGINT UNSIGNED | Yes | NULL | FK → users.id; RESTRICT | Approved by user id. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_stock_adjustments_4fe26025`: (adjustment_number).
- `ix_stock_adjustments_ae7720e8`: (created_by_user_id).
- `ix_stock_adjustments_46df84f0`: (approved_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`adjustment_number`)) > 0`.
- ``adjustment_type` IN ('correction', 'damaged')`.
- ``status` IN ('draft', 'finalized', 'cancelled')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## stock_adjustment_items

Module: `Inventory`. Model: [StockAdjustmentItem](../../app/Modules/Inventory/Models/StockAdjustmentItem.php).

Source / meaning: BRD p11 signed adjustment detail.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,spare_part_id,location_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| stock_adjustment_id | BIGINT UNSIGNED | No | No default | FK → stock_adjustments.id; RESTRICT; UNIQUE(stock_adjustment_id,spare_part_id,location_id) | Stock adjustment id. |
| spare_part_id | BIGINT UNSIGNED | No | No default | FK → spare_parts.id; RESTRICT; UNIQUE(stock_adjustment_id,spare_part_id,location_id); UNIQUE(id,spare_part_id,location_id) | Spare part id. |
| location_id | BIGINT UNSIGNED | No | No default | FK → stock_locations.id; RESTRICT; UNIQUE(stock_adjustment_id,spare_part_id,location_id); UNIQUE(id,spare_part_id,location_id) | Location id. |
| quantity_delta | DECIMAL(18,3) | No | 0 | — | Signed stock/adjustment quantity. Positive adds stock; negative removes stock. This row is not an independent cached balance. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_stock_adjustment_items_1e5daea3`: (stock_adjustment_id, spare_part_id, location_id).
- `uq_stock_adjustment_items_d9f67aa6`: (id, spare_part_id, location_id).
- `ix_stock_adjustment_items_a140820a`: (stock_adjustment_id).
- `ix_stock_adjustment_items_10e3c1ce`: (spare_part_id).
- `ix_stock_adjustment_items_876d7bdb`: (location_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``quantity_delta` <> 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## stock_counts

Module: `Inventory`. Model: [StockCount](../../app/Modules/Inventory/Models/StockCount.php).

Source / meaning: BRD p11 physical verification header.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| count_number | VARCHAR(60) | No | No default | UNIQUE(count_number) | Count number. Immutable through Eloquent after creation. |
| counted_at | DATETIME | No | No default | — | Counted at. |
| counted_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Counted by user id. |
| status | VARCHAR(64) | No | draft | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: draft, finalized, cancelled. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_stock_counts_568cf27e`: (count_number).
- `ix_stock_counts_c0622bff`: (counted_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`count_number`)) > 0`.
- ``status` IN ('draft', 'finalized', 'cancelled')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## stock_count_items

Module: `Inventory`. Model: [StockCountItem](../../app/Modules/Inventory/Models/StockCountItem.php).

Source / meaning: BRD p11 count observations; difference later posts explicitly to stock ledger.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,spare_part_id,location_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| stock_count_id | BIGINT UNSIGNED | No | No default | FK → stock_counts.id; RESTRICT; UNIQUE(stock_count_id,spare_part_id,location_id) | Stock count id. |
| spare_part_id | BIGINT UNSIGNED | No | No default | FK → spare_parts.id; RESTRICT; UNIQUE(stock_count_id,spare_part_id,location_id); UNIQUE(id,spare_part_id,location_id) | Spare part id. |
| location_id | BIGINT UNSIGNED | No | No default | FK → stock_locations.id; RESTRICT; UNIQUE(stock_count_id,spare_part_id,location_id); UNIQUE(id,spare_part_id,location_id) | Location id. |
| expected_quantity | DECIMAL(18,3) | No | 0 | — | Expected quantity. CHECK `expected_quantity` >= 0. |
| counted_quantity | DECIMAL(18,3) | No | 0 | — | Counted quantity. CHECK `counted_quantity` >= 0. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_stock_count_items_ee7e6c5a`: (stock_count_id, spare_part_id, location_id).
- `uq_stock_count_items_d9f67aa6`: (id, spare_part_id, location_id).
- `ix_stock_count_items_942c8300`: (stock_count_id).
- `ix_stock_count_items_10e3c1ce`: (spare_part_id).
- `ix_stock_count_items_876d7bdb`: (location_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``expected_quantity` >= 0`.
- ``counted_quantity` >= 0`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## stock_returns

Module: `Inventory`. Model: [StockReturn](../../app/Modules/Inventory/Models/StockReturn.php).

Source / meaning: BRD pp11,15 returns retain source transaction.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| return_number | VARCHAR(60) | No | No default | UNIQUE(return_number) | Return number. Immutable through Eloquent after creation. |
| return_type | VARCHAR(64) | No | No default | — | Return type. Allowed: repair_return, pos_return, supplier_return. |
| repair_job_id | BIGINT UNSIGNED | Yes | NULL | FK → repair_jobs.id; RESTRICT | Repair job id. |
| pos_sale_id | BIGINT UNSIGNED | Yes | NULL | FK → pos_sales.id; RESTRICT | Pos sale id. |
| purchase_id | BIGINT UNSIGNED | Yes | NULL | FK → purchases.id; RESTRICT | Purchase id. |
| returned_at | DATETIME | No | No default | — | Returned at. |
| recorded_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Recorded by user id. |
| status | VARCHAR(64) | No | draft | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: draft, finalized, cancelled. |
| reason | TEXT | No | No default | — | Reason. |
| idempotency_key | VARCHAR(100) | Yes | NULL | UNIQUE(idempotency_key) | Stable POS return retry key. |
| idempotency_hash | VARCHAR(64) | Yes | NULL | — | SHA-256 fingerprint of the normalized return request; rejects reuse of a key with changed data. |
| refund_amount | DECIMAL(19,2) | No | 0 | — | Authorized refund total associated with this return, if any. |
| refund_payment_reversal_id | BIGINT UNSIGNED | Yes | NULL | FK → payment_reversals.id; RESTRICT | Existing payment-ledger reversal created for an authorized POS refund. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_stock_returns_855a7dba`: (return_number).
- `uq_step10_pos_return_idempotency`: (idempotency_key).
- `ix_stock_returns_d2dc6852`: (repair_job_id).
- `ix_stock_returns_bc1ec442`: (pos_sale_id).
- `ix_stock_returns_86cc2ebe`: (purchase_id).
- `ix_stock_returns_06129e94`: (recorded_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `(`return_type` = 'repair_return' AND `repair_job_id` IS NOT NULL AND `pos_sale_id` IS NULL AND `purchase_id` IS NULL) OR (`return_type` = 'pos_return' AND `pos_sale_id` IS NOT NULL AND `repair_job_id` IS NULL AND `purchase_id` IS NULL) OR (`return_type` = 'supplier_return' AND `purchase_id` IS NOT NULL AND `repair_job_id` IS NULL AND `pos_sale_id` IS NULL)`.
- `LENGTH(TRIM(`return_number`)) > 0`.
- ``return_type` IN ('repair_return', 'pos_return', 'supplier_return')`.
- ``status` IN ('draft', 'finalized', 'cancelled')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## stock_return_items

Module: `Inventory`. Model: [StockReturnItem](../../app/Modules/Inventory/Models/StockReturnItem.php).

Source / meaning: BRD p11 returned stock detail; stock reversal links to original issue.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,spare_part_id,location_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| stock_return_id | BIGINT UNSIGNED | No | No default | FK → stock_returns.id; RESTRICT | Stock return id. |
| spare_part_id | BIGINT UNSIGNED | No | No default | FK → spare_parts.id; RESTRICT; UNIQUE(id,spare_part_id,location_id) | Spare part id. |
| location_id | BIGINT UNSIGNED | No | No default | FK → stock_locations.id; RESTRICT; UNIQUE(id,spare_part_id,location_id) | Location id. |
| quantity | DECIMAL(18,3) | No | 0 | — | Positive source-line quantity in its spare part unit. DECIMAL capacity does not approve fractional units; validate against unit.quantity_scale later. CHECK `quantity` > 0. |
| unit_cost | DECIMAL(19,4) | Yes | NULL | — | Historical known acquisition/consumption unit cost, four decimals. NULL, if allowed, means unknown; no FIFO/average valuation policy is selected. CHECK `unit_cost` >= 0. |
| condition | VARCHAR(64) | No | resalable | — | Condition. Allowed: resalable, damaged. |
| pos_sale_item_id | BIGINT UNSIGNED | Yes | NULL | FK → pos_sale_items.id; RESTRICT | Original POS sale line. Used to enforce cumulative return quantity limits. |
| refund_amount | DECIMAL(19,2) | No | 0 | — | Refund amount attributed to this returned line. |
| notes | TEXT | Yes | NULL | — | Notes. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_stock_return_items_d9f67aa6`: (id, spare_part_id, location_id).
- `ix_stock_return_items_7032963c`: (stock_return_id).
- `ix_stock_return_items_10e3c1ce`: (spare_part_id).
- `ix_stock_return_items_876d7bdb`: (location_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- ``quantity` > 0`.
- ``unit_cost` >= 0`.
- ``condition` IN ('resalable', 'damaged')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## stock_movements

Module: `Inventory`. Model: [StockMovement](../../app/Modules/Inventory/Models/StockMovement.php).

Source / meaning: BRD pp10-11; sole shared signed stock ledger; no mutable stock cache.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment; UNIQUE(id,spare_part_id,location_id) | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| spare_part_id | BIGINT UNSIGNED | No | No default | FK → spare_parts.id; RESTRICT; UNIQUE(id,spare_part_id,location_id) | Spare part id. |
| location_id | BIGINT UNSIGNED | No | No default | FK → stock_locations.id; RESTRICT; UNIQUE(opening_part_id,location_id); UNIQUE(id,spare_part_id,location_id) | Location id. |
| movement_type | VARCHAR(64) | No | No default | — | Movement type. Allowed: opening, purchase_receipt, job_issue, pos_sale, customer_return, supplier_return, adjustment, damaged, count_correction. |
| quantity_delta | DECIMAL(18,3) | No | 0 | — | Signed stock/adjustment quantity. Positive adds stock; negative removes stock. This row is not an independent cached balance. |
| unit_cost | DECIMAL(19,4) | Yes | NULL | — | Historical known acquisition/consumption unit cost, four decimals. NULL, if allowed, means unknown; no FIFO/average valuation policy is selected. CHECK `unit_cost` >= 0. |
| occurred_at | DATETIME | No | No default | — | Business event timestamp used for date-filtered ledgers, stock history, and audit queries. |
| reference_number | VARCHAR(80) | No | No default | — | Human-readable source reference retained with the immutable financial or stock event. |
| recorded_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Recorded by user id. |
| reason | TEXT | No | No default | — | Reason. |
| idempotency_key | VARCHAR(100) | No | No default | UNIQUE(idempotency_key) | Stable unique application operation key. Future retries must reuse this key rather than generate a fresh key per attempt. |
| grn_item_id | BIGINT UNSIGNED | Yes | NULL | FK → goods_received_note_items.id; RESTRICT; UNIQUE(grn_item_id) | Grn item id. |
| job_part_usage_id | BIGINT UNSIGNED | Yes | NULL | FK → job_part_usages.id; RESTRICT; UNIQUE(job_part_usage_id) | Job part usage id. |
| pos_sale_item_id | BIGINT UNSIGNED | Yes | NULL | FK → pos_sale_items.id; RESTRICT; UNIQUE(pos_sale_item_id) | Pos sale item id. |
| return_item_id | BIGINT UNSIGNED | Yes | NULL | FK → stock_return_items.id; RESTRICT; UNIQUE(return_item_id) | Return item id. |
| adjustment_item_id | BIGINT UNSIGNED | Yes | NULL | FK → stock_adjustment_items.id; RESTRICT; UNIQUE(adjustment_item_id) | Adjustment item id. |
| count_item_id | BIGINT UNSIGNED | Yes | NULL | FK → stock_count_items.id; RESTRICT; UNIQUE(count_item_id) | Count item id. |
| reverses_movement_id | BIGINT UNSIGNED | Yes | NULL | FK → stock_movements.id; RESTRICT | Original stock movement being compensated. Part/location are FK-enforced; partial-return quantity ceilings require future locks and validation. |
| opening_part_id | BIGINT UNSIGNED | Yes | GENERATED: CASE WHEN `movement_type` = 'opening' THEN `spare_part_id` ELSE NULL END | UNIQUE(opening_part_id,location_id) | Generated marker: one opening movement per part/location |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_stock_movements_d9f67aa6`: (id, spare_part_id, location_id).
- `uq_stock_movements_7db4ecce`: (idempotency_key).
- `uq_stock_movements_36e939cc`: (opening_part_id, location_id).
- `uq_stock_movements_59d7f54b`: (grn_item_id).
- `uq_stock_movements_53950c4b`: (job_part_usage_id).
- `uq_stock_movements_eba31e8f`: (pos_sale_item_id).
- `uq_stock_movements_81078615`: (return_item_id).
- `uq_stock_movements_ec123f6e`: (adjustment_item_id).
- `uq_stock_movements_28c2bb76`: (count_item_id).
- `fk_stock_movements_d942adbc`: (grn_item_id, spare_part_id, location_id).
- `fk_stock_movements_037ecddd`: (job_part_usage_id, spare_part_id, location_id).
- `fk_stock_movements_cc6702aa`: (pos_sale_item_id, spare_part_id, location_id).
- `fk_stock_movements_ad495a05`: (return_item_id, spare_part_id, location_id).
- `fk_stock_movements_0d8111ca`: (adjustment_item_id, spare_part_id, location_id).
- `fk_stock_movements_f4ec6a20`: (count_item_id, spare_part_id, location_id).
- `fk_stock_movements_693404d5`: (reverses_movement_id, spare_part_id, location_id).
- `ix_stock_movements_fa634624`: (spare_part_id, location_id, occurred_at).
- `ix_stock_movements_d084c0da`: (movement_type, occurred_at).
- `ix_stock_movements_10e3c1ce`: (spare_part_id).
- `ix_stock_movements_876d7bdb`: (location_id).
- `ix_stock_movements_06129e94`: (recorded_by_user_id).
- `ix_stock_movements_35647365`: (reverses_movement_id).

Composite foreign keys:

- (grn_item_id, spare_part_id, location_id) → `goods_received_note_items` (id, spare_part_id, location_id); RESTRICT delete/update.
- (job_part_usage_id, spare_part_id, location_id) → `job_part_usages` (id, spare_part_id, location_id); RESTRICT delete/update.
- (pos_sale_item_id, spare_part_id, location_id) → `pos_sale_items` (id, spare_part_id, location_id); RESTRICT delete/update.
- (return_item_id, spare_part_id, location_id) → `stock_return_items` (id, spare_part_id, location_id); RESTRICT delete/update.
- (adjustment_item_id, spare_part_id, location_id) → `stock_adjustment_items` (id, spare_part_id, location_id); RESTRICT delete/update.
- (count_item_id, spare_part_id, location_id) → `stock_count_items` (id, spare_part_id, location_id); RESTRICT delete/update.
- (reverses_movement_id, spare_part_id, location_id) → `stock_movements` (id, spare_part_id, location_id); RESTRICT delete/update.

Table CHECK rules:

- ``quantity_delta` <> 0`.
- `(`movement_type` IN ('job_issue','pos_sale','supplier_return','damaged') AND `quantity_delta` < 0) OR (`movement_type` IN ('opening','purchase_receipt','customer_return') AND `quantity_delta` > 0) OR (`movement_type` IN ('adjustment','count_correction'))`.
- ``movement_type` IN ('opening', 'purchase_receipt', 'job_issue', 'pos_sale', 'customer_return', 'supplier_return', 'adjustment', 'damaged', 'count_correction')`.
- ``unit_cost` >= 0`.
- `LENGTH(TRIM(`reference_number`)) > 0`.
- `LENGTH(TRIM(`idempotency_key`)) > 0`.
- `(`movement_type` = 'purchase_receipt' AND `grn_item_id` IS NOT NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'job_issue' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NOT NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'pos_sale' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NOT NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'customer_return' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NOT NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'supplier_return' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NOT NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'adjustment' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NOT NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'damaged' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NOT NULL AND `count_item_id` IS NULL) OR (`movement_type` = 'count_correction' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NOT NULL) OR (`movement_type` = 'opening' AND `grn_item_id` IS NULL AND `job_part_usage_id` IS NULL AND `pos_sale_item_id` IS NULL AND `return_item_id` IS NULL AND `adjustment_item_id` IS NULL AND `count_item_id` IS NULL)`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## expense_categories

Module: `Payments`. Model: [ExpenseCategory](../../app/Modules/Payments/Models/ExpenseCategory.php).

Source / meaning: BRD p14 conditional expenses; Step2 sec16 explicitly requests storage.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| name | VARCHAR(150) | No | No default | UNIQUE(name) | Name. |
| description | TEXT | Yes | NULL | — | Description. |
| is_active | BOOLEAN | No | 1 | — | Is active. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_expense_categories_6ae99955`: (name).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`name`)) > 0`.

Record policy: reference/configuration data; incoming foreign keys restrict deletion. Raw SQL and eventless operations bypass model event guards.

## expenses

Module: `Payments`. Model: [Expense](../../app/Modules/Payments/Models/Expense.php).

Source / meaning: Step2 sec16 expense storage; profit policy remains undecided.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| expense_number | VARCHAR(60) | No | No default | UNIQUE(expense_number) | Expense number. Immutable through Eloquent after creation. |
| expense_category_id | BIGINT UNSIGNED | Yes | NULL | FK → expense_categories.id; RESTRICT | Expense category id. |
| incurred_at | DATETIME | No | No default | — | Incurred at. |
| amount | DECIMAL(19,2) | No | 0 | — | Amount. CHECK `amount` > 0. |
| method | VARCHAR(64) | Yes | NULL | — | Method. Allowed: cash, bank_transfer, cheque. |
| description | TEXT | No | No default | — | Description. |
| payee | VARCHAR(255) | Yes | NULL | — | Payee. |
| external_reference | VARCHAR(100) | Yes | NULL | — | External reference. |
| recorded_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Recorded by user id. |
| status | VARCHAR(64) | No | draft | — | Document or job lifecycle status; distinct from derived invoice payment status. Allowed: draft, finalized, cancelled. |
| cancelled_at | DATETIME | Yes | NULL | — | Cancelled at. |
| cancellation_reason | TEXT | Yes | NULL | — | Cancellation reason. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `uq_expenses_b958d1d5`: (expense_number).
- `ix_expenses_4545e26c`: (incurred_at).
- `ix_expenses_478b68a9`: (expense_category_id).
- `ix_expenses_06129e94`: (recorded_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `LENGTH(TRIM(`expense_number`)) > 0`.
- ``amount` > 0`.
- ``method` IN ('cash', 'bank_transfer', 'cheque')`.
- ``status` IN ('draft', 'finalized', 'cancelled')`.

Record policy: retained through Eloquent; cancel/reverse instead of deleting. Raw SQL and eventless operations bypass model event guards.

## document_print_histories

Module: `PrintTemplates`. Model: [DocumentPrintHistory](../../app/Modules/PrintTemplates/Models/DocumentPrintHistory.php).

Source / meaning: BRD pp5,7,9-10,17; typed references and immutable reprint snapshot.

| Field | SQL type | Nullable | Default | Key / reference | Meaning and validation |
| --- | --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | No | No default | PK, auto increment | Database-generated internal identifier; primary key Immutable through Eloquent after creation. |
| print_template_id | BIGINT UNSIGNED | Yes | NULL | FK → print_templates.id; RESTRICT | Print template id. |
| repair_receipt_id | BIGINT UNSIGNED | Yes | NULL | FK → repair_receipts.id; RESTRICT | Repair receipt id. |
| job_card_id | BIGINT UNSIGNED | Yes | NULL | FK → job_cards.id; RESTRICT | Job card id. |
| quotation_id | BIGINT UNSIGNED | Yes | NULL | FK → quotations.id; RESTRICT | Quotation id. |
| invoice_id | BIGINT UNSIGNED | Yes | NULL | FK → invoices.id; RESTRICT | Invoice id. |
| pos_sale_id | BIGINT UNSIGNED | Yes | NULL | FK → pos_sales.id; RESTRICT | Pos sale id. |
| payment_id | BIGINT UNSIGNED | Yes | NULL | FK → payments.id; RESTRICT | Payment id. |
| customer_id | BIGINT UNSIGNED | Yes | NULL | FK → customers.id; RESTRICT | Registered customer owning this transaction; nullable only where anonymous POS is supported. |
| purchase_id | BIGINT UNSIGNED | Yes | NULL | FK → purchases.id; RESTRICT | Purchase id. |
| printed_at | DATETIME | No | No default | — | Printed at. |
| printed_by_user_id | BIGINT UNSIGNED | No | No default | FK → users.id; RESTRICT | Printed by user id. |
| is_duplicate | BOOLEAN | No | 0 | — | Is duplicate. |
| duplicate_label | VARCHAR(100) | Yes | NULL | — | Duplicate label. |
| document_snapshot | JSON | No | No default | — | Issued document data/layout snapshot for historically accurate reprints; exclude credentials and unnecessary personal data. |
| output_reference | VARCHAR(255) | Yes | NULL | — | Output reference. |
| created_at | DATETIME | Yes | NULL | — | ORM record creation timestamp; no business event is implied. |
| updated_at | DATETIME | Yes | NULL | — | ORM last modification timestamp; append-only records retain their original row. |

Indexes (including unique candidate keys):

- `PRIMARY`: (id).
- `ix_document_print_histories_52a2214d`: (print_template_id).
- `ix_document_print_histories_66747a19`: (repair_receipt_id).
- `ix_document_print_histories_cf053e5b`: (job_card_id).
- `ix_document_print_histories_596c572d`: (quotation_id).
- `ix_document_print_histories_15d25c20`: (invoice_id).
- `ix_document_print_histories_bc1ec442`: (pos_sale_id).
- `ix_document_print_histories_fcaec7df`: (payment_id).
- `ix_document_print_histories_a7a13f4c`: (customer_id).
- `ix_document_print_histories_86cc2ebe`: (purchase_id).
- `ix_document_print_histories_432b819f`: (printed_by_user_id).

Composite foreign keys:

- None; single-column references are listed above.

Table CHECK rules:

- `(CASE WHEN `repair_receipt_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `job_card_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `quotation_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `invoice_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `pos_sale_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `payment_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `customer_id` IS NOT NULL THEN 1 ELSE 0 END) + (CASE WHEN `purchase_id` IS NOT NULL THEN 1 ELSE 0 END) = 1`.
- ``document_snapshot` IS NULL OR JSON_VALID(`document_snapshot`)`.

Record policy: append-only via Eloquent; corrections create linked evidence. Raw SQL and eventless operations bypass model event guards.

## Retained Laravel infrastructure

These existing tables are preserved. `jobs` is Laravel's queue, not repair_jobs. Password reset storage does not enable public reset routes. The users.password field contains a password hash; no employee password field is added.

### cache

| Field | Native type | Nullable | Default | Key | Meaning |
| --- | --- | --- | --- | --- | --- |
| key | varchar(255) | NO | No explicit default | PRI | Laravel framework infrastructure field. |
| value | mediumtext | NO | No explicit default | — | Laravel framework infrastructure field. |
| expiration | bigint(20) | NO | No explicit default | MUL | Laravel framework infrastructure field. |

### cache_locks

| Field | Native type | Nullable | Default | Key | Meaning |
| --- | --- | --- | --- | --- | --- |
| key | varchar(255) | NO | No explicit default | PRI | Laravel framework infrastructure field. |
| owner | varchar(255) | NO | No explicit default | — | Laravel framework infrastructure field. |
| expiration | bigint(20) | NO | No explicit default | MUL | Laravel framework infrastructure field. |

### failed_jobs

| Field | Native type | Nullable | Default | Key | Meaning |
| --- | --- | --- | --- | --- | --- |
| id | bigint(20) unsigned | NO | No explicit default | PRI | Laravel framework infrastructure field. |
| uuid | varchar(255) | NO | No explicit default | UNI | Laravel framework infrastructure field. |
| connection | varchar(255) | NO | No explicit default | MUL | Laravel framework infrastructure field. |
| queue | varchar(255) | NO | No explicit default | — | Laravel framework infrastructure field. |
| payload | longtext | NO | No explicit default | — | Laravel framework infrastructure field. |
| exception | longtext | NO | No explicit default | — | Laravel framework infrastructure field. |
| failed_at | timestamp | NO | current_timestamp() | — | Laravel framework infrastructure field. |

### jobs

| Field | Native type | Nullable | Default | Key | Meaning |
| --- | --- | --- | --- | --- | --- |
| id | bigint(20) unsigned | NO | No explicit default | PRI | Laravel framework infrastructure field. |
| queue | varchar(255) | NO | No explicit default | MUL | Laravel framework infrastructure field. |
| payload | longtext | NO | No explicit default | — | Laravel framework infrastructure field. |
| attempts | smallint(5) unsigned | NO | No explicit default | — | Laravel framework infrastructure field. |
| reserved_at | int(10) unsigned | YES | NULL | — | Laravel framework infrastructure field. |
| available_at | int(10) unsigned | NO | No explicit default | — | Laravel framework infrastructure field. |
| created_at | int(10) unsigned | NO | No explicit default | — | Laravel framework infrastructure field. |

### job_batches

| Field | Native type | Nullable | Default | Key | Meaning |
| --- | --- | --- | --- | --- | --- |
| id | varchar(255) | NO | No explicit default | PRI | Laravel framework infrastructure field. |
| name | varchar(255) | NO | No explicit default | — | Laravel framework infrastructure field. |
| total_jobs | int(11) | NO | No explicit default | — | Laravel framework infrastructure field. |
| pending_jobs | int(11) | NO | No explicit default | — | Laravel framework infrastructure field. |
| failed_jobs | int(11) | NO | No explicit default | — | Laravel framework infrastructure field. |
| failed_job_ids | longtext | NO | No explicit default | — | Laravel framework infrastructure field. |
| options | mediumtext | YES | NULL | — | Laravel framework infrastructure field. |
| cancelled_at | int(11) | YES | NULL | — | Laravel framework infrastructure field. |
| created_at | int(11) | NO | No explicit default | — | Laravel framework infrastructure field. |
| finished_at | int(11) | YES | NULL | — | Laravel framework infrastructure field. |

### migrations

| Field | Native type | Nullable | Default | Key | Meaning |
| --- | --- | --- | --- | --- | --- |
| id | int(10) unsigned | NO | No explicit default | PRI | Laravel framework infrastructure field. |
| migration | varchar(255) | NO | No explicit default | — | Laravel framework infrastructure field. |
| batch | int(11) | NO | No explicit default | — | Laravel framework infrastructure field. |

### password_reset_tokens

| Field | Native type | Nullable | Default | Key | Meaning |
| --- | --- | --- | --- | --- | --- |
| email | varchar(255) | NO | No explicit default | PRI | Laravel framework infrastructure field. |
| token | varchar(255) | NO | No explicit default | — | Laravel framework infrastructure field. |
| created_at | timestamp | YES | NULL | — | Laravel framework infrastructure field. |

### sessions

| Field | Native type | Nullable | Default | Key | Meaning |
| --- | --- | --- | --- | --- | --- |
| id | varchar(255) | NO | No explicit default | PRI | Laravel framework infrastructure field. |
| user_id | bigint(20) unsigned | YES | NULL | MUL | Laravel framework infrastructure field. |
| ip_address | varchar(45) | YES | NULL | — | Laravel framework infrastructure field. |
| user_agent | text | YES | NULL | — | Laravel framework infrastructure field. |
| payload | longtext | NO | No explicit default | — | Laravel framework infrastructure field. |
| last_activity | int(11) | NO | No explicit default | MUL | Laravel framework infrastructure field. |

### users

| Field | Native type | Nullable | Default | Key | Meaning |
| --- | --- | --- | --- | --- | --- |
| id | bigint(20) unsigned | NO | No explicit default | PRI | Existing login identity primary key. |
| name | varchar(255) | NO | No explicit default | — | Account display name. |
| email | varchar(255) | YES | NULL | UNI | Optional contact address; no longer the login identifier. Existing addresses remain intact. |
| username | varchar(64) | YES during migration; populated for existing rows | No explicit default | UNI (`uq_users_username`) | Normalized username login identifier. New accounts require one; migration derives existing usernames from unique email local parts. |
| email_verified_at | timestamp | YES | NULL | — | Optional verification timestamp; no verification workflow is introduced. |
| password | varchar(255) | NO | No explicit default | — | Hash only; Laravel's hashed cast is preserved. |
| is_active | boolean | NO | 1 | — | Disabled accounts cannot authenticate and are rejected on authenticated web requests. |
| last_login_at | datetime | YES | NULL | — | Timestamp of the latest successful login. |
| remember_token | varchar(100) | YES | NULL | — | Sensitive persistent-login token; hidden and excluded/redacted from audit snapshots. |
| created_at | timestamp | YES | NULL | — | Existing Laravel account timestamp. |
| updated_at | timestamp | YES | NULL | — | Existing Laravel account timestamp. |

## Derived business values (not duplicate columns)

Step 10 reuses existing POS, inventory, payment, return, sequence, audit, and print-history tables. Sale items preserve description, quantity, price, line total and last known non-null location stock cost; `document_snapshot.items` preserves SKU/name at checkout. Transactional balances use locking current reads. Refund ceilings allocate sale-wide discounts proportionally to returned quantities, rounded down to currency precision. Returns reference the original POS item and an optional shared payment reversal. The ERD includes these links and the verifying user.

| Value | Authoritative source / formula |
| --- | --- |
| Spare part opening stock | SUM(stock_movements.quantity_delta) for movement_type=opening, by part/location. |
| Spare part current stock | SUM(all committed stock_movements.quantity_delta), by part/location. No separate POS stock or mutable current_stock column. |
| Customer account balance | SUM(customer_account_entries.amount). Include one opening entry; never add customers.opening_balance again. |
| Supplier account balance | SUM(supplier_account_entries.amount). Include one opening entry; never add suppliers.opening_balance again. |
| Net applied payment | SUM(allocation.amount) minus SUM(its allocation releases.amount). Releases are not new payments. |
| Invoice outstanding | Finalized invoice.total minus net direct invoice allocations and eligible job advances from linked repair jobs. Step 8 rejects overpayment and clamps display outstanding to zero; reversals/releases restore outstanding. |
| Invoice payment status | Derived as Unpaid if net applied=0; Partially Paid if 0<net<total; Paid if net>=total for a finalized positive-total invoice. Canceled state remains separate. Never set Paid manually. |
| Purchase settled amount | Net supplier allocations to that purchase. |
| Job advances | Net payment allocations whose target is that job. The linked invoice balance includes eligible advances for jobs on the invoice; customer-level unapplied advances are allocated at issue without a second cash posting. |

Step 8 implements payment posting, invoice collection locks, allocation/reversal releases, customer-ledger entries, and audit logging. SQLite feature tests exercise these services; deployment MySQL contention still needs a separate environment test. Stock deduction and full reporting remain later phases.
