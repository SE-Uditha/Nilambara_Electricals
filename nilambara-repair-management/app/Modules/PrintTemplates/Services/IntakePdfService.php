<?php

namespace App\Modules\PrintTemplates\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;

class IntakePdfService
{
    public function download(View $view, string $filename)
    {
        $data = $view->getData();
        $html = $view->render();
        // Embed only local public images; PDF generation never fetches a URL.
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            foreach ($document->getElementsByTagName('img') as $image) {
                $url = $image->getAttribute('src');
                $path = parse_url($url, PHP_URL_PATH);
                $public = realpath(public_path());
                $absolute = is_string($path) ? realpath(public_path(ltrim($path, '/'))) : false;
                if ($absolute && str_starts_with(strtolower($absolute), strtolower($public.DIRECTORY_SEPARATOR)) && in_array(mime_content_type($absolute), ['image/png', 'image/jpeg'], true)) {
                    $image->setAttribute('src', 'data:'.mime_content_type($absolute).';base64,'.base64_encode(file_get_contents($absolute)));
                } else {
                    $image->removeAttribute('src');
                }
            }
            $html = $document->saveHTML();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $font = str_replace('\\', '/', public_path('fonts/NotoSansSinhala-Regular.ttf'));
        $bold = str_replace('\\', '/', public_path('fonts/NotoSansSinhala-Bold.ttf'));
        $css = '<style>@font-face{font-family:NotoSinhala;src:url("'.$font.'") format("truetype");font-style:normal;font-weight:400}@font-face{font-family:NotoSinhala;src:url("'.$bold.'") format("truetype");font-style:normal;font-weight:700}body{font-family:NotoSinhala,"DejaVu Sans",sans-serif}.screen-actions{display:none}.document{border:0;padding:0}.preview{width:100%}.page{width:100%;min-height:0;height:auto;border:0;padding:0}.page .writing{min-height:10mm}.page .writing.large{min-height:18mm}.page .line{margin:2px 0}.page .section{margin-top:4px}.page .value{min-height:3mm}.page .print-date{position:static;margin-top:2mm}.grid,.meta,.reference{display:block}.grid>.field,.meta>div,.reference>div{display:inline-block;width:48%;vertical-align:top}.wide{width:100%!important}.business,.signature,.title-row,.doc-title{display:block}.signature>div{display:inline-block;width:46%}.business img{float:left;margin-right:10px}</style>';
        $css .= '<style>.document{line-height:1.15}.document .section{margin-top:8px}.document .section h3{margin-bottom:3px}.document .reference{font-size:9pt;padding:7px}.document .signature{margin-top:16px}.document .print-date{margin-top:8px}</style>';
        $html = str_replace('</head>', $css.'</head>', $html);

        return Pdf::loadHTML($html)->setOptions(['isRemoteEnabled' => false])->setPaper(strtolower($data['paperSize']), $data['orientation'])->download($filename);
    }
}
