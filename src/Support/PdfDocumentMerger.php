<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Une PDFs existentes (p. ej. reglamento + hoja de firma) usando FPDF/FPDI vendored.
 */
final class PdfDocumentMerger
{
    private static bool $autoloadRegistered = false;

    public static function registerAutoload(): void
    {
        if (self::$autoloadRegistered) {
            return;
        }
        self::$autoloadRegistered = true;

        $fpdf = dirname(__DIR__, 2) . '/lib/fpdf/fpdf.php';
        if (is_file($fpdf) && !class_exists('FPDF', false)) {
            require $fpdf;
        }

        spl_autoload_register(static function (string $class): void {
            $prefix = 'setasign\\Fpdi\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = dirname(__DIR__, 2) . '/lib/fpdi/' . $relative . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }

    /**
     * Concatena PDFs en orden. Devuelve bytes del PDF resultante.
     *
     * @param list<string> $absolutePdfPaths
     */
    public static function mergeToFile(array $absolutePdfPaths, string $outputAbsolutePath): void
    {
        self::registerAutoload();
        if ($absolutePdfPaths === []) {
            throw new \RuntimeException('No hay PDFs para unir.');
        }
        foreach ($absolutePdfPaths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('PDF no legible: ' . basename((string) $path));
            }
        }

        if (!class_exists('FPDF', false)) {
            throw new \RuntimeException('FPDF no está disponible.');
        }

        $pdf = new class extends \setasign\Fpdi\Fpdi {
        };

        foreach ($absolutePdfPaths as $path) {
            $pageCount = $pdf->setSourceFile($path);
            for ($page = 1; $page <= $pageCount; $page++) {
                $tpl = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($tpl);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }
        }

        $dir = dirname($outputAbsolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear la carpeta del PDF firmado.');
        }
        $pdf->Output('F', $outputAbsolutePath);
        if (!is_file($outputAbsolutePath) || filesize($outputAbsolutePath) < 50) {
            throw new \RuntimeException('No se pudo generar el PDF unido.');
        }
    }

    public static function isPdfFile(string $absolutePath): bool
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return false;
        }
        $fh = fopen($absolutePath, 'rb');
        if ($fh === false) {
            return false;
        }
        $head = fread($fh, 5);
        fclose($fh);

        return is_string($head) && str_starts_with($head, '%PDF');
    }
}
