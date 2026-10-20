# Operator handover documents

Version 1.0, 20 October 2026. All four PDFs were rendered and visually reviewed; automated text-bound and replacement-glyph checks reported no warnings.

- [Daily user guidance](../../output/pdf/daily-user-guidance.pdf) - 3 pages, quick daily workflow.
- [User manual](../../output/pdf/user-manual.pdf) - 5 pages, staff workflows and correction boundaries.
- [Administrator manual](../../output/pdf/administrator-manual.pdf) - 4 pages, local operation, access, settings, backup and recovery.
- [System test report](../../output/pdf/system-test-report.pdf) - 44 pages, consolidated results, detailed schema evidence, defects and pending UAT.

Editable Markdown sources are alongside this index. The test-report source is compiled from the maintained reports under `docs/testing/`. It preserves historical findings and identifies the superseding follow-up findings. The complete BRD matrix and automated method inventory remain repository companion evidence.

Regenerate from the application directory with the configured PHP environment:

```powershell
$env:PHPRC = (Resolve-Path ..\.tools\php.ini).Path
python scripts/create-handover-documents.py
python scripts/check-handover-pdfs.py
```

Generation uses installed Composer libraries (CommonMark and DomPDF); validation uses Python PyMuPDF and Pillow. Recheck the rendered pages under `tmp/pdfs/handover-review/` after changes. Manual contents are defined in the generator; update those definitions before regeneration.

This documentation task did not rerun business tests or modify application data. The report describes the existing verified 218-test / 3566-assertion run. Approved backup storage, real Drive setup, hardware checks, capacity acceptance, cost-allocation policy and owner signatures remain outstanding.
