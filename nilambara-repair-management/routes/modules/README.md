# Future module routes

This directory contains the employee/account and role-permission routes from Step 3 and the receipt, repair-job, and job-card routes delivered in Step 5. Other modules remain reserved for later business implementation; no fake CRUD endpoints are registered.

`routes/web.php` registers module route files through Laravel's route facilities. Add a file only when its controller/component, authorization, views, and tests exist. Keep file names unique, avoid duplicate registration, name routes consistently, and apply authentication to business screens. Follow the registration convention already present in `routes/web.php` rather than adding a second loader.

Module directories alone do not require routes. Customer, receipt/job/card, sales, inventory, reports, audit, print settings, backup, and other modules receive routes only when their capability is implemented.
