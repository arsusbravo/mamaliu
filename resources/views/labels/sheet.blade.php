<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: 'DejaVuSansCondensed', sans-serif; }
        .bg, .label { position: absolute; width: {{ $format['label_width'] }}mm; height: {{ $format['label_height'] }}mm; }
        .label { text-align: center; color: {{ $textColor }}; }
        .logo { width: {{ $logoWidth }}mm; }
        .name { font-weight: bold; font-size: {{ $fontSize }}pt; line-height: 1.15; width: {{ $textWidth }}mm; margin: 0 auto; padding-top: 2mm; }
        .description { font-size: {{ $descriptionSize }}pt; line-height: 1.15; width: {{ $textWidth }}mm; margin: 0 auto; padding-top: 1.5mm; }
    </style>
</head>
<body>
@for ($r = 0; $r < $format['rows']; $r++)
    @for ($c = 0; $c < $format['cols']; $c++)
        @php
            $left = $format['margin_left'] + $c * $format['label_width'];
            $top = $format['margin_top'] + $r * $format['label_height'];
        @endphp
        @if ($backgroundFile)
            <div class="bg" style="left: {{ $left }}mm; top: {{ $top }}mm;">
                <img src="{{ $backgroundFile }}" style="width: {{ $format['label_width'] }}mm; height: {{ $format['label_height'] }}mm;">
            </div>
        @endif
        <div class="label" style="left: {{ $left }}mm; top: {{ $top }}mm; padding-top: {{ $offsetTop }}mm;">
            @if ($showLogo)
                <img class="logo" src="{{ public_path('images/smallogo.png') }}">
            @endif
            <div class="name">{{ $title }}</div>
            @if (filled($description))
                <div class="description">{!! nl2br(e($description)) !!}</div>
            @endif
        </div>
    @endfor
@endfor
</body>
</html>
