<?php

// Label sheet presets (A4, millimetres). Margins centre the grid on the page.
$format = function (string $name, int $cols, int $rows, float $width, float $height): array {
    return [
        'name' => $name,
        'cols' => $cols,
        'rows' => $rows,
        'label_width' => $width,
        'label_height' => $height,
        'margin_left' => round((210 - $cols * $width) / 2, 2),
        'margin_top' => round((297 - $rows * $height) / 2, 2),
    ];
};

return [
    'default' => 'pcl12',

    'formats' => [
        'pcl12' => $format('PCL12', 3, 4, 63.5, 72),
        '2x2' => $format('2 x 2', 2, 2, 105, 148.5),
        '2x4' => $format('2 x 4', 2, 4, 105, 74.25),
        '3x8' => $format('3 x 8', 3, 8, 70, 37),
        '2x7' => $format('2 x 7', 2, 7, 99.1, 38.1),
    ],
];
