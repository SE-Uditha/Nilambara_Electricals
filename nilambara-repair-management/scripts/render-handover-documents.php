<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use League\CommonMark\GithubFlavoredMarkdownConverter;

$root = dirname(__DIR__);
$converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);
$manifest = json_decode(file_get_contents($root.'/docs/manuals/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($manifest as $document) {
    $title = htmlspecialchars($document['title']);
    $body = $converter->convert(file_get_contents($root.'/docs/manuals/'.$document['slug'].'.md'))->getContent();
    $html = '<html><head><meta charset="utf-8"><style>
    @page { margin: 52pt 45pt 48pt; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #243348; }
    h1 { font-size: 23pt; line-height:1.2; color:#123d51; margin: 0 0 18pt; page-break-before:always; }
    h2 { font-size: 13pt; color:#123d51; margin:17pt 0 7pt; page-break-after:avoid; }
    h3 { font-size: 10pt; margin:12pt 0 5pt; page-break-after:avoid; }
    p { margin:0 0 8pt; } li { margin-bottom:5pt; }
    table { border-collapse: collapse; width:100%; font-size:7.5pt; margin:10pt 0; table-layout:fixed; }
    td, th { border: .5pt solid #ccd6df; padding:5pt; overflow-wrap:break-word; }
    th { background:#edf3f7; } tr { page-break-inside:avoid; }
    pre { white-space: pre-wrap; font-size:8pt; background:#edf3f7; padding:9pt; }
    code { font-family:DejaVu Sans Mono; font-size:7.5pt; overflow-wrap:break-word; }
    a { color:#14627d; text-decoration:none; }
    .cover { page-break-after:always; padding-top:110pt; }
    .cover h1 { page-break-before:avoid; font-size:30pt; }
    .label { color:#14627d; font-size:11pt; letter-spacing:1pt; }
    .meta { margin-top:35pt; border-top:2pt solid #14627d; padding-top:14pt; }
    </style></head><body><div class="cover"><p class="label">NILAMBARA ELECTRICALS</p><h1>'.$title.'</h1><p>Repair Shop Management System</p><div class="meta"><p>Version 1.0 | '.$document['date'].'</p><p>Step 12 follow-up handover</p><p>Software verification complete for the recorded test scope.<br>Business acceptance and operational configuration items remain pending.</p></div></div>'.$body.'</body></html>';
    $options = new Options;
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('chroot', $root);
    $pdf = new Dompdf($options);
    $pdf->setPaper('A4');
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->render();
    $pdf->getCanvas()->page_text(45, 811, 'Nilambara Electricals | '.$title.' | 20 Oct 2026', 'Helvetica', 7, [0.3, 0.36, 0.42]);
    $pdf->getCanvas()->page_text(495, 811, '{PAGE_NUM} / {PAGE_COUNT}', 'Helvetica', 7, [0.3, 0.36, 0.42]);
    $target = $root.'/output/pdf/'.$document['slug'].'.pdf';
    file_put_contents($target, $pdf->output());
    echo $document['slug'].': '.filesize($target)." bytes\n";
}
