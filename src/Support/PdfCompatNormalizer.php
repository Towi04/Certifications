<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reescribe PDFs modernos (xref comprimido / object streams) a un formato
 * que FPDI free pueda importar.
 *
 * Orden: qpdf vendored/sistema → Ghostscript → PDF compatible embebido en
 * resources/regulations (p. ej. reglamento UKS).
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

        $errors = [];

        try {
            $viaQpdf = self::normalizeWithQpdf($absolutePdfPath);
            if ($viaQpdf !== null) {
                return self::persistCompatibleCopy($absolutePdfPath, $viaQpdf);
            }
        } catch (\Throwable $e) {
            $errors[] = 'qpdf: ' . $e->getMessage();
        }

        $viaGs = self::tryGhostscript($absolutePdfPath);
        if ($viaGs !== null) {
            return self::persistCompatibleCopy($absolutePdfPath, $viaGs);
        }
        $errors[] = 'ghostscript: no disponible o falló';

        $bundled = self::bundledCompatiblePath($absolutePdfPath);
        if ($bundled !== null) {
            $dir = dirname($absolutePdfPath);
            $out = $dir . '/.fpdi-bundled-' . bin2hex(random_bytes(6)) . '.pdf';
            if (@copy($bundled, $out) && is_file($out) && filesize($out) > 50) {
                return self::persistCompatibleCopy($absolutePdfPath, $out);
            }
            $errors[] = 'recurso embebido: no se pudo copiar';
        } else {
            $errors[] = 'recurso embebido: sin coincidencia para ' . basename($absolutePdfPath);
        }

        throw new \RuntimeException(
            'El PDF del reglamento usa compresión incompatible con el unidor PDF. '
            . implode(' · ', $errors)
        );
    }

    /**
     * Sustituye el PDF original en disco por la versión compatible (deja .pre-fpdi.bak).
     * Así las siguientes firmas no dependen de qpdf en el hosting.
     */
    private static function persistCompatibleCopy(string $originalAbs, string $compatibleAbs): string
    {
        if ($compatibleAbs === $originalAbs) {
            return $originalAbs;
        }
        if (!is_file($compatibleAbs) || filesize($compatibleAbs) < 50) {
            return $compatibleAbs;
        }
        if (self::needsNormalization($compatibleAbs)) {
            return $compatibleAbs;
        }

        $bak = $originalAbs . '.pre-fpdi.bak';
        if (!is_file($bak)) {
            @copy($originalAbs, $bak);
        }
        if (@rename($compatibleAbs, $originalAbs) || (@copy($compatibleAbs, $originalAbs) && @unlink($compatibleAbs))) {
            return $originalAbs;
        }

        return $compatibleAbs;
    }

    public static function needsNormalization(string $absolutePdfPath): bool
    {
        $fh = fopen($absolutePdfPath, 'rb');
        if ($fh === false) {
            return true;
        }
        $head = (string) fread($fh, 16);
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

        $probe = $head . $tail;
        if (str_contains($probe, '/ObjStm') || preg_match('/\/Type\s*\/XRef\b/', $probe) === 1) {
            return true;
        }

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
        $candidates = [];

        $wrapper = dirname(__DIR__, 2) . '/lib/qpdf/qpdf.sh';
        if (is_file($wrapper)) {
            $candidates[] = $wrapper;
        }
        $vendored = dirname(__DIR__, 2) . '/lib/qpdf/bin/qpdf';
        if (is_file($vendored)) {
            $candidates[] = $vendored;
        }

        $which = self::which('qpdf');
        if ($which !== null) {
            $candidates[] = $which;
        }
        foreach (['/usr/bin/qpdf', '/usr/local/bin/qpdf', '/opt/cpanel/ea-php*/root/usr/bin/qpdf'] as $path) {
            if (str_contains($path, '*')) {
                continue;
            }
            if (is_file($path)) {
                $candidates[] = $path;
            }
        }

        foreach ($candidates as $path) {
            @chmod($path, 0755);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function normalizeWithQpdf(string $absolutePdfPath): ?string
    {
        $qpdf = self::resolveQpdfBinary();
        if ($qpdf === null) {
            return null;
        }

        $dir = dirname($absolutePdfPath);
        $out = $dir . '/.fpdi-' . bin2hex(random_bytes(6)) . '-' . basename($absolutePdfPath);
        $args = [
            '--object-streams=disable',
            '--compress-streams=y',
            '--recompress-flate',
            $absolutePdfPath,
            $out,
        ];

        $result = self::runCommand($qpdf, $args, self::vendoredLibDir());
        if ($result['code'] !== 0 || !is_file($out) || filesize($out) < 50) {
            @unlink($out);
            $detail = trim($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']);
            throw new \RuntimeException(
                'falló al convertir'
                . ($detail !== '' ? ' (' . mb_substr($detail, 0, 200) . ')' : '')
            );
        }
        if (self::needsNormalization($out)) {
            @unlink($out);

            return null;
        }

        return $out;
    }

    /**
     * PDFs compatibles versionados en el repo (hostings sin qpdf/proc_open).
     */
    private static function bundledCompatiblePath(string $absolutePdfPath): ?string
    {
        $base = basename($absolutePdfPath);
        $map = [
            'cdb49512545d4639_Reglamento_Clientes_DOCEO-UKS_compressed.pdf' => 'uks-doceo-fpdi.pdf',
            'Reglamento_Clientes_DOCEO-UKS_compressed.pdf' => 'uks-doceo-fpdi.pdf',
        ];
        $name = $map[$base] ?? null;
        if ($name === null) {
            // Coincidencia flexible por nombre típico UKS Doceo.
            if (preg_match('/Reglamento_Clientes_DOCEO-UKS/i', $base) === 1
                || preg_match('/REGLAMENTO_UKS/i', $base) === 1
            ) {
                $name = 'uks-doceo-fpdi.pdf';
            }
        }
        if ($name === null) {
            return null;
        }
        $path = dirname(__DIR__, 2) . '/resources/regulations/' . $name;
        if (!is_file($path) || !is_readable($path) || filesize($path) < 50) {
            return null;
        }
        if (self::needsNormalization($path)) {
            return null;
        }

        return $path;
    }

    /**
     * @param list<string> $args
     * @return array{code:int,stdout:string,stderr:string}
     */
    private static function runCommand(string $binary, array $args, ?string $libDir): array
    {
        $cmd = array_merge([$binary], $args);
        $env = null;
        if ($libDir !== null) {
            $env = getenv();
            if (!is_array($env)) {
                $env = [];
            }
            $prev = (string) ($env['LD_LIBRARY_PATH'] ?? '');
            $env['LD_LIBRARY_PATH'] = $libDir . ($prev !== '' ? ':' . $prev : '');
        }

        if (function_exists('proc_open')) {
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = @proc_open($cmd, $descriptor, $pipes, null, $env);
            if (is_resource($proc)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]) ?: '';
                $stderr = stream_get_contents($pipes[2]) ?: '';
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($proc);

                return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
            }
        }

        // Fallback cuando proc_open está deshabilitado en el hosting.
        $parts = [escapeshellarg($binary)];
        foreach ($args as $arg) {
            $parts[] = escapeshellarg($arg);
        }
        $line = implode(' ', $parts);
        if ($libDir !== null) {
            $line = 'env LD_LIBRARY_PATH=' . escapeshellarg($libDir) . ' ' . $line;
        }

        $stdout = '';
        $code = 1;
        if (function_exists('exec') && !self::functionDisabled('exec')) {
            $output = [];
            @exec($line . ' 2>&1', $output, $code);
            $stdout = implode("\n", $output);
        } elseif (function_exists('shell_exec') && !self::functionDisabled('shell_exec')) {
            $stdout = (string) @shell_exec($line . ' 2>&1');
            $outFile = $args[array_key_last($args)] ?? '';
            $code = (is_string($outFile) && is_file($outFile) && filesize($outFile) > 50) ? 0 : 1;
        } else {
            throw new \RuntimeException('proc_open/exec deshabilitados en el servidor');
        }

        return ['code' => (int) $code, 'stdout' => $stdout, 'stderr' => ''];
    }

    private static function functionDisabled(string $fn): bool
    {
        $disabled = (string) ini_get('disable_functions');
        if ($disabled === '') {
            return false;
        }
        $list = array_map('trim', explode(',', strtolower($disabled)));

        return in_array(strtolower($fn), $list, true);
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
            if (is_file($candidate)) {
                @chmod($candidate, 0755);

                return $candidate;
            }
        }

        return null;
    }

    private static function tryGhostscript(string $absolutePdfPath): ?string
    {
        $gs = self::which('gs') ?? (is_file('/usr/bin/gs') ? '/usr/bin/gs' : null);
        if ($gs === null) {
            return null;
        }
        @chmod($gs, 0755);
        $out = dirname($absolutePdfPath) . '/.fpdi-gs-' . bin2hex(random_bytes(6)) . '.pdf';
        try {
            $result = self::runCommand($gs, [
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.4',
                '-dNOPAUSE',
                '-dBATCH',
                '-dQUIET',
                '-sOutputFile=' . $out,
                $absolutePdfPath,
            ], null);
        } catch (\Throwable) {
            @unlink($out);

            return null;
        }
        if ($result['code'] !== 0 || !is_file($out) || filesize($out) < 50 || self::needsNormalization($out)) {
            @unlink($out);

            return null;
        }

        return $out;
    }
}
