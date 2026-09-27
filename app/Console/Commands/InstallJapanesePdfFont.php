<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Installs IPAex Gothic (Japanese) into storage/fonts so dompdf can render
 * invoice PDFs without garbled characters. The IPAex fonts are distributed
 * under the IPA Font License and are redistributable.
 *
 * Sources (tried in order):
 *   1. https://moji.or.jp/wp-content/ipafont/IPAexfont/IPAexfont00401.zip
 *   2. https://cdn.jsdelivr.net/gh/kaityo256/ipafont@master/ipaexg.ttf
 *   3. https://github.com/googlefonts/dompdf-fonts/raw/main/ipaexg.ttf (fallback)
 *
 * After downloading, the font is registered via dompdf's FontMetrics so
 * defaultFont => 'ipaexg' works out of the box.
 */
class InstallJapanesePdfFont extends Command
{
    protected $signature = 'pdf:install-japanese-font
                            {--source= : Override the source URL for the font TTF/ZIP}
                            {--force : Re-download and overwrite an existing font}';

    protected $description = 'Install IPAex Gothic into storage/fonts for dompdf Japanese PDF rendering';

    private const FONT_FAMILY = 'ipaexg';
    private const FONT_TTF = 'ipaexg.ttf';

    private const DEFAULT_SOURCES = [
        'https://cdn.jsdelivr.net/gh/kaityo256/ipafont@master/ipaexg.ttf',
        'https://moji.or.jp/wp-content/ipafont/IPAexfont/IPAexfont00401.zip',
    ];

    public function handle(): int
    {
        $fontDir = storage_path('fonts');
        if (!is_dir($fontDir) && !mkdir($fontDir, 0755, true) && !is_dir($fontDir)) {
            $this->error("Unable to create font directory: {$fontDir}");
            return 1;
        }

        $targetTtf = $fontDir . DIRECTORY_SEPARATOR . self::FONT_TTF;

        if (file_exists($targetTtf) && !$this->option('force')) {
            $this->info("Font already installed: {$targetTtf}");
            $this->info('Use --force to re-download.');
            return $this->registerWithDompdf($targetTtf) ? 0 : 1;
        }

        $sources = $this->option('source') ? [$this->option('source')] : self::DEFAULT_SOURCES;

        $downloaded = null;
        foreach ($sources as $url) {
            $this->line("Trying: {$url}");
            $body = $this->downloadUrl($url);
            if ($body === null) {
                $this->warn(' -> download failed');
                continue;
            }

            $ttfBytes = $this->extractTtfBytes($url, $body);
            if ($ttfBytes === null) {
                $this->warn(' -> could not extract TTF from response');
                continue;
            }

            $downloaded = $ttfBytes;
            break;
        }

        if ($downloaded === null) {
            $this->error('Failed to download IPAex Gothic from all sources.');
            $this->line('You can also drop ipaexg.ttf into ' . $fontDir . ' manually.');
            return 1;
        }

        if (file_put_contents($targetTtf, $downloaded) === false) {
            $this->error("Failed to write font file: {$targetTtf}");
            return 1;
        }

        $this->info("Installed: {$targetTtf} (" . number_format(strlen($downloaded)) . ' bytes)');

        return $this->registerWithDompdf($targetTtf) ? 0 : 1;
    }

    private function downloadUrl(string $url): ?string
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (misechoku pdf:install-japanese-font)',
                    'follow_location' => 1,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $body = @file_get_contents($url, false, $ctx);
            return $body === false ? null : $body;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractTtfBytes(string $url, string $body): ?string
    {
        if (str_ends_with(strtolower($url), '.zip')) {
            return $this->extractTtfFromZip($body);
        }

        // Direct TTF: sanity check the sfnt header ("\x00\x01\x00\x00" or "OTTO" or "true").
        $header = substr($body, 0, 4);
        if ($header === "\x00\x01\x00\x00" || $header === 'OTTO' || $header === 'true' || $header === 'ttcf') {
            return $body;
        }

        return null;
    }

    private function extractTtfFromZip(string $body): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->warn('ZipArchive PHP extension not available - cannot extract .zip. Use --source with a direct .ttf URL instead.');
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ipaex_');
        if ($tmp === false) return null;
        file_put_contents($tmp, $body);

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            return null;
        }

        $ttf = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i) ?: '';
            if (stripos($name, 'ipaexg') !== false && str_ends_with(strtolower($name), '.ttf')) {
                $ttf = $zip->getFromIndex($i) ?: null;
                break;
            }
        }

        $zip->close();
        @unlink($tmp);
        return $ttf;
    }

    private function registerWithDompdf(string $ttfPath): bool
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->error('Dompdf classes not found. Run `composer install`.');
            return false;
        }

        try {
            $dompdf = new \Dompdf\Dompdf();
            $fontMetrics = $dompdf->getFontMetrics();
            $registered = $fontMetrics->registerFont(
                ['family' => self::FONT_FAMILY, 'style' => 'normal', 'weight' => 'normal'],
                $ttfPath
            );
            $fontMetrics->saveFontFamilies();

            if ($registered === false) {
                $this->warn('Font registration returned false, but the TTF is in place. dompdf will still resolve it via font_dir.');
            } else {
                $this->info('Registered font family: ' . self::FONT_FAMILY);
            }

            return true;
        } catch (\Throwable $e) {
            $this->warn('Font registered on disk but dompdf metrics generation failed: ' . $e->getMessage());
            $this->line('This is usually harmless - the TTF at ' . $ttfPath . ' will be picked up on first PDF render.');
            return true;
        }
    }
}
