# Future module routes

This directory reserves module route files for later business implementation. Step 1 intentionally contains no business route files or fake CRUD endpoints.

`routes/web.php` registers module route files through Laravel's route facilities. Add a file only when its controller/component, authorization, views, and tests exist. Keep file names unique, avoid duplicate registration, name routes consistently, and apply authentication to business screens. Follow the registration convention already present in `routes/web.php` rather than adding a second loader.

Module directories alone do not require routes. Reports, audit, print settings, and other modules receive routes when their capability is implemented.
