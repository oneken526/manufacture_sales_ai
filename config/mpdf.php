<?php

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

$defaultConfig = (new ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

return [
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font_size' => 10,
    'default_font' => 'meiryo',

    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 16,
    'margin_bottom' => 16,
    'margin_header' => 9,
    'margin_footer' => 9,

    'tempDir' => storage_path('app/mpdf'),

    // 日本語帳票対応: Windows環境にバンドルされているMeiryo(TrueType Collection)を参照する。
    // 本番環境(Linux等)では IPAex Gothic 等の再配布可能な日本語フォントを resources/fonts に配置し、
    // fontDir / fontdata のパスをそちらに切り替えること。
    'fontDir' => array_merge($fontDirs, [
        'C:\\Windows\\Fonts',
    ]),

    'fontdata' => $fontData + [
        'meiryo' => [
            'R' => 'meiryo.ttc',
            'B' => 'meiryob.ttc',
            'TTCfontID' => [
                'R' => 1,
                'B' => 1,
            ],
            'useOTL' => 0xFF,
        ],
    ],
];
