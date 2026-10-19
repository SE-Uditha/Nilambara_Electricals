# Quotation printing and PDF

Quotation documents render from the saved quotation snapshot, so later customer/job edits do not silently change issued documents. Draft previews are clearly watermarked as drafts.

## Output

- Browser print preview: `/quotations/{quotation}/print?paper=A4` or `paper=A5`.
- PDF download: `/quotations/{quotation}/pdf?paper=A4` or `paper=A5`.
- Duplicate browser reprint: `/quotations/{quotation}/duplicate`; the configured duplicate-copy label is shown and persisted.
- The A4 and A5 portrait layouts include customer/job facts, proposed repair, estimated parts and labour, validity, terms, approval state, business identity/logo, and signature spaces. PDF and HTML outputs are recorded in `document_print_histories` and `audit_logs`.
- Logo files must be PNG/JPEG assets under `public/`; remote images are not enabled. Sinhala uses the included Noto Sans Sinhala regular/bold fonts; the bundled font license is in `public/fonts/OFL-NotoSansSinhala.txt`.

## Runtime requirements and verification

Enable PHP's `gd` extension for DomPDF output with the business PNG logo. The ignored workspace `.tools/php.ini` now loads GD; other setups must enable it in the PHP configuration used by both Artisan and the web server, then restart PHP. DomPDF also needs write access to `storage/fonts/` for its generated font cache.

`php artisan test` covers the database workflows when GD is enabled. If GD is available but not enabled in the active INI, run:

```powershell
php -d extension=gd vendor\bin\phpunit tests\Feature\QuotationManagementTest.php
```

The verified rendering asserts valid PDF signatures, embedded Noto Sinhala font metadata, and A4/A5 page dimensions. This is code-level validation, not physical printer acceptance or final business-owner layout approval. If PDF output is deployed without GD, configure GD before enabling the PDF action.
