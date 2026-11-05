<?php

namespace App\Support;

use Mpdf\Mpdf;

/**
 * Renders an A4 sheet filled with copies of a single label.
 */
class LabelSheet
{
    use HandlesImageUploads;

    private const PT_TO_MM = 0.3528;

    /**
     * @param  array  $format  one entry of config('labels.formats')
     * @param  string|null  $background  absolute path of a background image
     */
    public static function pdf(
        array $format,
        string $title,
        ?string $description = null,
        bool $showLogo = true,
        string $textColor = '#000000',
        ?string $background = null,
    ): string {
        return (new self)->render($format, $title, $description, $showLogo, $textColor, $background);
    }

    private function render(array $format, string $title, ?string $description, bool $showLogo, string $textColor, ?string $background): string
    {
        $textWidth = $format['label_width'] - 8;

        // Shrink the font for longer names so they stay inside the label
        $titleLength = mb_strlen($title);
        $fontSize = match (true) {
            $titleLength <= 12 => 20,
            $titleLength <= 24 => 16,
            $titleLength <= 40 => 13,
            default => 10,
        };
        $fontSize = min($fontSize, max(8, (int) round($format['label_width'] / 3.5)));
        $descriptionSize = max(7, (int) round($fontSize * 0.6));

        // Estimate the content height (logo + wrapped text) to centre it vertically
        $logoWidth = min($format['label_width'] * 0.45, $format['label_height'] * 0.5);
        $logoHeight = $showLogo ? $logoWidth * 172 / 200 : 0;
        $contentHeight = $logoHeight
            + 2 + $this->lineCount($title, $fontSize, $textWidth) * $fontSize * self::PT_TO_MM * 1.15;
        if ($description !== null && trim($description) !== '') {
            $contentHeight += 1.5 + $this->lineCount($description, $descriptionSize, $textWidth) * $descriptionSize * self::PT_TO_MM * 1.15;
        }
        $offsetTop = max(1, ($format['label_height'] - $contentHeight) / 2);

        $backgroundFile = $background ? $this->coverBackground($background, $format) : null;

        try {
            $html = view('labels.sheet', [
                'format' => $format,
                'title' => $title,
                'description' => $description,
                'showLogo' => $showLogo,
                'textColor' => $textColor,
                'backgroundFile' => $backgroundFile,
                'fontSize' => $fontSize,
                'descriptionSize' => $descriptionSize,
                'logoWidth' => round($logoWidth, 1),
                'textWidth' => $textWidth,
                'offsetTop' => round($offsetTop, 1),
            ])->render();

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);
            $mpdf->SetDefaultFont('DejaVuSansCondensed');
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');
        } finally {
            if ($backgroundFile) {
                @unlink($backgroundFile);
            }
        }
    }

    private function lineCount(string $text, int $fontSize, float $width): int
    {
        $charWidth = $fontSize * self::PT_TO_MM * 0.6;
        $lines = 0;
        foreach (preg_split('/\R/', $text) as $line) {
            $lines += max(1, (int) ceil(mb_strlen($line) * $charWidth / $width));
        }

        return $lines;
    }

    /**
     * Scale the background to the full label width (no side cropping) at ~300 dpi
     * and anchor it to the top: cropped at the bottom if taller than the label,
     * on a white canvas if shorter. Written to a temp file for mPDF.
     */
    private function coverBackground(string $path, array $format): string
    {
        $width = (int) round($format['label_width'] / 25.4 * 300);
        $height = (int) round($format['label_height'] / 25.4 * 300);

        $manager = $this->imageManager();
        $image = $manager->read($path)->scale(width: $width);

        $canvas = $manager->create($width, $height)->fill('ffffff');
        // place() anchors the image at the top (so e.g. a corner ribbon stays in the
        // corner) and clips it to the canvas if it is taller than the label
        $canvas->place($image, 'top');

        $temp = tempnam(sys_get_temp_dir(), 'label') . '.jpg';
        $canvas->toJpeg(90)->save($temp);

        return $temp;
    }
}
