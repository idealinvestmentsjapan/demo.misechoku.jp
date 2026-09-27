<?php

// Published from vendor/barryvdh/laravel-dompdf/config/dompdf.php and tuned for
// Japanese invoice PDF rendering. See INSTALL: `php artisan pdf:install-japanese-font`.

return [
    'show_warnings' => false,
    'public_path' => null,
    'convert_entities' => true,

    'options' => [
        // Fonts live under storage/fonts. The Japanese font (ipaexg.ttf) is registered
        // by the pdf:install-japanese-font command, then referenced via defaultFont.
        'font_dir' => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),

        'temp_dir' => sys_get_temp_dir(),

        // chroot must include both public and storage/fonts so fonts declared via
        // @font-face or resolved by defaultFont can be read at render time.
        'chroot' => [
            realpath(base_path()),
            storage_path('fonts'),
        ],

        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://' => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        'artifactPathValidation' => null,

        'log_output_file' => null,
        'enable_font_subsetting' => true,
        'pdf_backend' => 'CPDF',

        // ipaexg = IPAex Gothic. Installed by pdf:install-japanese-font. If the font
        // is missing, dompdf falls back to DejaVu Sans (garbled JP) - so the invoice
        // PDF route guards against that state explicitly.
        'default_font' => 'ipaexg',
        'dpi' => 96,
        'font_height_ratio' => 1.1,

        'enable_php' => false,
        'enable_javascript' => true,
        'enable_remote' => true,
        'allowed_remote_hosts' => null,

        'enable_html5_parser' => true,
    ],
];
