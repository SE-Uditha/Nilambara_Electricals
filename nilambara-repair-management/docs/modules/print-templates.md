# Print templates

`/settings/print-templates` and the existing `/print-templates` routes expose authorized preview, versioned edits and reset. `Step11TemplateSeeder` creates missing document types only: repair receipt, A6 job card, quotation, A5 invoice, thermal POS, customer statement, payment receipt and purchase layout metadata. It does not replace existing versions or create transactions. Purchase printing depends on the separate purchasing workflow.

Settings include business identity, logo asset, title, font, paper/orientation, margins, thermal width, column percentages, additional signature labels, footer and duplicate label. Column widths and labels are validated JSON lists; all content is escaped. Executable templates, JavaScript and PHP are not accepted. Mandatory document fields and signature areas remain present.

Invoice paper is A5 and job cards A6; quotation supports A4/A5, POS thermal/A4/A5. Reset creates a new active version. Preview/test layout is explicitly a layout example, not a fake transaction. Receipts, job cards, quotations, invoices, POS, payments and customer statements reuse their existing renderers and snapshots. Physical paper/printer acceptance and Sinhala complex-script visual review require the shop's actual environment.
