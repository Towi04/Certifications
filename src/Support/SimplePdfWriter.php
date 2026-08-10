<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generador mínimo de PDF (texto + PNG RGB/RGBA) sin dependencias.
 * Pensado para constancias de firma digital.
 */
final class SimplePdfWriter
{
    /** @var list<string> */
    private array $lines = [];

    private ?string $pngPath = null;

    private float $pngMaxWidth = 280;

    private float $pngMaxHeight = 90;

    /** @param list<string> $lines */
    public function __construct(array $lines = [])
    {
        $this->lines = $lines;
    }

    public function addLine(string $text): self
    {
        $this->lines[] = $text;

        return $this;
    }

    /** @param list<string> $lines */
    public function addLines(array $lines): self
    {
        foreach ($lines as $line) {
            $this->addLine((string) $line);
        }

        return $this;
    }

    public function setPngImage(?string $absolutePath, float $maxWidth = 280, float $maxHeight = 90): self
    {
        $this->pngPath = $absolutePath;
        $this->pngMaxWidth = $maxWidth;
        $this->pngMaxHeight = $maxHeight;

        return $this;
    }

    public function write(string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear la carpeta del PDF.');
        }
        $pdf = $this->build();
        if (@file_put_contents($absolutePath, $pdf) === false) {
            throw new \RuntimeException('No se pudo guardar el PDF.');
        }
    }

    public function build(): string
    {
        $pageW = 612.0;
        $pageH = 792.0;
        $margin = 50.0;
        $y = $pageH - $margin;
        $fontSize = 11.0;
        $leading = 15.0;

        $content = "BT\n/F1 {$fontSize} Tf\n";
        $content .= sprintf("1 0 0 1 %.2F %.2F Tm\n", $margin, $y);
        $first = true;
        foreach ($this->lines as $line) {
            $safe = $this->escapeText($this->toWinAnsi($line));
            if ($first) {
                $content .= "({$safe}) Tj\n";
                $first = false;
            } else {
                $content .= "0 -{$leading} Td\n({$safe}) Tj\n";
            }
            $y -= $leading;
            if ($y < 120) {
                break;
            }
        }
        $content .= "ET\n";

        $imageObj = null;
        $imageBytes = null;
        $imgW = 0;
        $imgH = 0;
        $drawW = 0.0;
        $drawH = 0.0;
        if ($this->pngPath && is_file($this->pngPath)) {
            $decoded = $this->decodePng($this->pngPath);
            if ($decoded !== null) {
                $imgW = $decoded['width'];
                $imgH = $decoded['height'];
                $imageBytes = $decoded['data'];
                $scale = min($this->pngMaxWidth / max(1, $imgW), $this->pngMaxHeight / max(1, $imgH), 1.0);
                $drawW = $imgW * $scale;
                $drawH = $imgH * $scale;
                $imgY = max(60.0, $y - $drawH - 20);
                $content .= sprintf(
                    "q\n%.2F 0 0 %.2F %.2F %.2F cm\n/Im1 Do\nQ\n",
                    $drawW,
                    $drawH,
                    $margin,
                    $imgY
                );
                $imageObj = true;
            }
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';

        $resources = '<< /Font << /F1 5 0 R /F2 6 0 R >>';
        if ($imageObj) {
            $resources .= ' /XObject << /Im1 7 0 R >>';
        }
        $resources .= ' >>';

        $objects[3] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Contents 4 0 R /Resources %s >>',
            $pageW,
            $pageH,
            $resources
        );
        $objects[4] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>';

        if ($imageObj && $imageBytes !== null) {
            $objects[7] = '<< /Type /XObject /Subtype /Image'
                . ' /Width ' . $imgW
                . ' /Height ' . $imgH
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8'
                . ' /Filter /FlateDecode'
                . ' /Length ' . strlen($imageBytes)
                . " >>\nstream\n" . $imageBytes . "\nendstream";
        }

        $out = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $max = max(array_keys($objects));
        $out .= "xref\n0 " . ($max + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $out .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\n";
        $out .= "startxref\n{$xref}\n%%EOF";

        return $out;
    }

    private function escapeText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /** Aproxima UTF-8 a WinAnsi para Helvetica built-in. */
    private function toWinAnsi(string $text): string
    {
        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Ñ' => 'N', 'ñ' => 'n', 'Ü' => 'U', 'ü' => 'u', '¿' => '?', '¡' => '!',
            '–' => '-', '—' => '-', '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
        ];
        $text = strtr($text, $map);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted === false) {
            return preg_replace('/[^\x20-\x7E]/', '?', $text) ?: $text;
        }

        return $converted;
    }

    /**
     * @return array{width:int,height:int,data:string}|null
     */
    private function decodePng(string $path): ?array
    {
        $bin = @file_get_contents($path);
        if ($bin === false || strlen($bin) < 24 || !str_starts_with($bin, "\x89PNG\r\n\x1a\n")) {
            return null;
        }
        $pos = 8;
        $width = 0;
        $height = 0;
        $bitDepth = 8;
        $colorType = 2;
        $idat = '';
        $lenAll = strlen($bin);
        while ($pos + 8 <= $lenAll) {
            $len = unpack('N', substr($bin, $pos, 4));
            if ($len === false) {
                break;
            }
            $chunkLen = $len[1];
            $type = substr($bin, $pos + 4, 4);
            $data = substr($bin, $pos + 8, $chunkLen);
            $pos += 12 + $chunkLen;
            if ($type === 'IHDR') {
                $ihdr = unpack('Nwidth/Nheight/CbitDepth/CcolorType', $data);
                if ($ihdr === false) {
                    return null;
                }
                $width = (int) $ihdr['width'];
                $height = (int) $ihdr['height'];
                $bitDepth = (int) $ihdr['bitDepth'];
                $colorType = (int) $ihdr['colorType'];
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }
        if ($width < 1 || $height < 1 || $idat === '' || $bitDepth !== 8) {
            return null;
        }
        if (!in_array($colorType, [2, 6], true)) {
            // Solo RGB / RGBA (canvas HTML5 suele ser RGBA)
            return null;
        }
        $raw = @zlib_decode($idat);
        if ($raw === false || $raw === '') {
            return null;
        }
        $channels = $colorType === 6 ? 4 : 3;
        $stride = $width * $channels;
        $rgb = '';
        $offset = 0;
        for ($y = 0; $y < $height; $y++) {
            if ($offset >= strlen($raw)) {
                return null;
            }
            $filter = ord($raw[$offset]);
            $offset++;
            $row = substr($raw, $offset, $stride);
            $offset += $stride;
            if (strlen($row) < $stride) {
                return null;
            }
            if ($filter === 1) { // Sub
                for ($i = $channels; $i < $stride; $i++) {
                    $row[$i] = chr((ord($row[$i]) + ord($row[$i - $channels])) & 0xFF);
                }
            } elseif ($filter === 2) { // Up
                if ($y > 0 && isset($prevRow)) {
                    for ($i = 0; $i < $stride; $i++) {
                        $row[$i] = chr((ord($row[$i]) + ord($prevRow[$i])) & 0xFF);
                    }
                }
            } elseif ($filter === 3) { // Average
                for ($i = 0; $i < $stride; $i++) {
                    $left = $i >= $channels ? ord($row[$i - $channels]) : 0;
                    $up = ($y > 0 && isset($prevRow)) ? ord($prevRow[$i]) : 0;
                    $row[$i] = chr((ord($row[$i]) + intdiv($left + $up, 2)) & 0xFF);
                }
            } elseif ($filter === 4) { // Paeth
                for ($i = 0; $i < $stride; $i++) {
                    $a = $i >= $channels ? ord($row[$i - $channels]) : 0;
                    $b = ($y > 0 && isset($prevRow)) ? ord($prevRow[$i]) : 0;
                    $c = ($y > 0 && isset($prevRow) && $i >= $channels) ? ord($prevRow[$i - $channels]) : 0;
                    $p = $a + $b - $c;
                    $pa = abs($p - $a);
                    $pb = abs($p - $b);
                    $pc = abs($p - $c);
                    $pr = ($pa <= $pb && $pa <= $pc) ? $a : (($pb <= $pc) ? $b : $c);
                    $row[$i] = chr((ord($row[$i]) + $pr) & 0xFF);
                }
            } elseif ($filter !== 0) {
                return null;
            }
            $prevRow = $row;
            for ($x = 0; $x < $width; $x++) {
                $i = $x * $channels;
                $r = ord($row[$i]);
                $g = ord($row[$i + 1]);
                $b = ord($row[$i + 2]);
                if ($channels === 4) {
                    $a = ord($row[$i + 3]) / 255;
                    // Composite sobre blanco
                    $r = (int) round($r * $a + 255 * (1 - $a));
                    $g = (int) round($g * $a + 255 * (1 - $a));
                    $b = (int) round($b * $a + 255 * (1 - $a));
                }
                $rgb .= chr($r) . chr($g) . chr($b);
            }
        }

        $compressed = zlib_encode($rgb, ZLIB_ENCODING_DEFLATE);
        if ($compressed === false) {
            return null;
        }

        return ['width' => $width, 'height' => $height, 'data' => $compressed];
    }
}
