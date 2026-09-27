<?php

namespace App\Services;

use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF rendering via mPDF. mPDF bundles CJK fonts (sun-exta / sun-extb) inside
 * its Composer package, so no manual font install step is required on servers.
 * Replaces the previous barryvdh/laravel-dompdf integration which needed
 * ipaexg.ttf placed under storage/fonts to avoid tofu characters.
 */
class PdfService
{
    public static function download(string $view, array $data, string $filename): Response
    {
        return self::respond($view, $data, $filename, 'attachment');
    }

    public static function stream(string $view, array $data, string $filename): Response
    {
        return self::respond($view, $data, $filename, 'inline');
    }

    private static function respond(string $view, array $data, string $filename, string $disposition): Response
    {
        $html = View::make($view, $data)->render();

        $mpdf = new \Mpdf\Mpdf(self::options());
        $mpdf->WriteHTML($html);
        $body = $mpdf->Output('', 'S');

        $encoded = rawurlencode($filename);

        return new Response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                "%s; filename=\"%s\"; filename*=UTF-8''%s",
                $disposition,
                $filename,
                $encoded
            ),
        ]);
    }

    private static function options(): array
    {
        return [
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'sun-exta',
            'default_font_size' => 11,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            // Auto-select bundled CJK font (sun-exta) whenever the text is
            // detected as Japanese, so authors can leave font-family blank.
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => storage_path('framework/cache'),
        ];
    }
}
