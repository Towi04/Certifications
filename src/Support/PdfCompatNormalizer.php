<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reescribe PDFs modernos (xref comprimido / object streams) a un formato
 * que FPDI free pueda importar, usando qpdf del sistema o el binario vendored.
 */
final class PdfCompatNormalizer
{
    /**
     * Devuelve una ruta absoluta legible por FPDI.
     * Si el PDF ya es compatible, regresa el original.
     * Si requiere conversión y falla, lanza RuntimeException.
     */
    public static function ensureFpdiCompatible(string $absolutePdfPath): string
    {
        if (!is_file($absolutePdfPath) || !is_readable($absolutePdfPath)) {
            throw new \RuntimeException('PDF no legible para normalizar.');
        }
        if (!self::needsNormalization($absolutePdfPath)) {
            return $absolutePdfPath;
        }

        $qpdf = self::resolveQpdfBinary();
        if ($qpdf === null) {
            throw new \RuntimeException(
                'El PDF del reglamento usa compresión incompatible con el unidor PDF '
                . 'y no hay qpdf disponible para convertirlo.'
            );
        }

        $dir = dirname($absolutePdfPath);
        $out = $dir . '/.fpdi-' . bin2hex(random_bytes(6)) . '-' . basename($absolutePdfPath);
        $cmd = [
            $qpdf,
            '--object-streams=disable',
            '--compress-streams=y',
            '--recompress-flate',
            $absolutePdfPath,
            $out,
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = null;
        $libDir = self::vendoredLibDir();
        if ($libDir !== null) {
            $env = getenv();
            if (!is_array($env)) {
                $env = [];
            }
            $prev = (string) ($env['LD_LIBRARY_PATH'] ?? '');
            $env['LD_LIBRARY_PATH'] = $libDir . ($prev !== '' ? ':' . $prev : '');
        }

        $proc = @proc_open($cmd, $descriptor, $pipes, null, $env);
        if (!is_resource($proc)) {
            throw new \RuntimeException('No se pudo ejecutar qpdf para normalizar el PDF.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code !== 0 || !is_file($out) || filesize($out) < 50) {
            @unlink($out);
            $detail = trim($stderr !== '' ? $stderr : $stdout);
            throw new \RuntimeException(
                'qpdf no pudo normalizar el PDF'
                . ($detail !== '' ? ': ' . mb_substr($detail, 0, 240) : '.')
            );
        }

        if (self::needsNormalization($out)) {
            // Aún incompatible: intentar ghostscript si existe.
            @unlink($out);
            $gsOut = self::tryGhostscript($absolutePdfPath);
            if ($gsOut !== null) {
                return $gsOut;
            }
            throw new \RuntimeException('El PDF sigue siendo incompatible tras normalizar con qpdf.');
        }

        return $out;
    }

    public static function needsNormalization(string $absolutePdfPath): bool
    {
        $fh = fopen($absolutePdfPath, 'rb');
        if ($fh === false) {
            return true;
        }
        $head = (string) fread($fh, 16);
        // Leer cola para detectar xref stream sin cargar todo el archivo en memoria.
        $size = filesize($absolutePdfPath) ?: 0;
        $tailSize = (int) min(65536, max(0, $size));
        $tail = '';
        if ($tailSize > 0) {
            fseek($fh, -$tailSize, SEEK_END);
            $tail = (string) fread($fh, $tailSize);
        }
        fclose($fh);

        if (!str_starts_with($head, '%PDF')) {
            return true;
        }

        // Heurística: object streams o xref comprimido ⇒ FPDI free falla.
        $probe = $head . $tail;
        if (str_contains($probe, '/ObjStm') || preg_match('/\/Type\s*\/XRef\b/', $probe) === 1) {
            return true;
        }

        // Algunos PDFs traen ObjStm solo en el cuerpo: muestreo ligero.
        if ($size > 80000) {
            $mid = (string) file_get_contents($absolutePdfPath, false, null, (int) max(0, intdiv($size, 2) - 20000), 40000);
            if (str_contains($mid, '/ObjStm')) {
                return true;
            }
        } elseif ($size > 0) {
            $all = (string) file_get_contents($absolutePdfPath);
            if (str_contains($all, '/ObjStm') || preg_match('/\/Type\s*\/XRef\b/', $all) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function resolveQpdfBinary(): ?string
    {
        $vendored = dirname(__DIR__, 2) . '/lib/qpdf/bin/qpdf';
        if (is_file($vendored) && is_executable($vendored)) {
            return $vendored;
        }

        $which = self::which('qpdf');
        if ($which !== null) {
            return $which;
        }

        foreach (['/usr/bin/qpdf', '/usr/local/bin/qpdf'] as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function vendoredLibDir(): ?string
    {
        $dir = dirname(__DIR__, 2) . '/lib/qpdf/lib';

        return is_dir($dir) ? $dir : null;
    }

    private static function which(string $bin): ?string
    {
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
        foreach (explode(':', $path) as $dir) {
            $candidate = rtrim($dir, '/') . '/' . $bin;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function tryGhostscript(string $absolutePdfPath): ?string
    {
        $gs = self::which('gs') ?? (is_executable('/usr/bin/gs') ? '/usr/bin/gs' : null);
        if ($gs === null) {
            return null;
        }
        $out = dirname($absolutePdfPath) . '/.fpdi-gs-' . bin2hex(random_bytes(6)) . '.pdf';
        $cmd = [
            $gs,
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dNOPAUSE',
            '-dBATCH',
            '-dQUIET',
            '-sOutputFile=' . $out,
            $absolutePdfPath,
        ];
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $descriptor, $pipes);
        if (!is_resource($proc)) {
            return null;
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0 || !is_file($out) || filesize($out) < 50 || self::needsNormalization($out)) {
            @unlink($out);

            return null;
        }

        return $out;
    }
}
