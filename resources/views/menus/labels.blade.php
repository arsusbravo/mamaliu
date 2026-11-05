<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: 'DejaVuSansCondensed', sans-serif; }
        .label { position: absolute; text-align: center; width: {{ $format['label_width'] }}mm; height: {{ $format['label_height'] }}mm; }
        .logo { width: {{ $logoWidth }}mm; }
        .name { font-weight: bold; font-size: {{ $fontSize }}pt; line-height: 1.15; width: {{ $textWidth }}mm; margin: 0 auto; padding-top: 2mm; }
    </style>
</head>
<body>
@for ($r = 0; $r < $format['rows']; $r++)
    @for ($c = 0; $c < $format['cols']; $c++)
        <div class="label" style="left: {{ $format['margin_left'] + $c * $format['label_width'] }}mm; top: {{ $format['margin_top'] + $r * $format['label_height'] }}mm; padding-top: {{ $offsetTop }}mm;">
            <img class="logo" src="{{ public_path('images/smallogo.png') }}">
            <div class="name">{{ $menu->label }}</div>
        </div>
    @endfor
@endfor
</body>
</html>
