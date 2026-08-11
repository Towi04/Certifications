<?php

declare(strict_types=1);

namespace App\Documents;

use App\Catalog\CatalogRepository;
use App\Config\Env;
use App\Support\PdfDocumentMerger;
use App\Support\SimplePdfWriter;
use App\Support\Uploader;

final class RegulationSignService
{
    public function __construct(private readonly CatalogRepository $repo)
    {
    }

    /**
     * Guarda PNG desde data URL del canvas (o null si solo tipográfica).
     *
     * @return array{pdf_path:string, signature_path:?string, mode:string}
     */
    public function sign(
        int $caseId,
        string $signerName,
        ?int $documentId,
        ?int $userId,
        string $mode,
        ?string $signatureDataUrl
    ): array {
        $signerName = trim($signerName);
        if ($signerName === '') {
            throw new \RuntimeException('Escribe tu nombre completo para firmar el reglamento.');
        }
        $mode = in_array($mode, ['draw', 'type'], true) ? $mode : 'type';

        $signatureRel = null;
        $signatureAbs = null;
        if ($mode === 'draw') {
            $png = $this->dataUrlToPngBytes($signatureDataUrl);
            if ($png === null) {
                throw new \RuntimeException('Dibuja tu firma en el recuadro o elige firmar con tu nombre escrito.');
            }
            $signatureRel = $this->storeBinary('cases/' . $caseId . '/signatures', $png, 'png');
            $signatureAbs = Uploader::absolutePath($signatureRel);
        }

        $this->repo->ensureRegulationSignatureColumns();
        $signedAt = date('Y-m-d H:i:s');
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }
        $doc = $documentId ? $this->repo->document($documentId) : null;
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
        $appUrl = rtrim((string) (Env::get('APP_URL', 'https://pdv.institutodoceo.com') ?? ''), '/');

        $pdfRel = $this->buildSignedPdf(
            $caseId,
            $case,
            $doc,
            $signerName,
            $signedAt,
            $mode,
            $ip,
            $appUrl,
            $signatureAbs
        );

        $this->repo->updateCertificationCase($caseId, [
            'regulation_document_id' => $documentId,
            'regulation_signed_at' => $signedAt,
            'regulation_signer_name' => $signerName,
            'regulation_signed_pdf_path' => $pdfRel,
            'regulation_signature_path' => $signatureRel,
            'regulation_signature_mode' => $mode,
        ]);

        $this->repo->completeCurrentStepMatching(
            $caseId,
            ['reglamento', 'firmar'],
            $userId,
            'Reglamento firmado digitalmente por: ' . $signerName . ' (' . $mode . ')'
        );

        $this->repo->addCaseAttachment(
            $caseId,
            'regulation_signed_pdf',
            'Reglamento con hoja de firma — ' . $signerName,
            $pdfRel,
            $userId
        );
        if ($signatureRel) {
            $this->repo->addCaseAttachment(
                $caseId,
                'regulation_signature_image',
                'Imagen de firma — ' . $signerName,
                $signatureRel,
                $userId
            );
        }

        try {
            $html = $this->buildHtmlReceipt($caseId, $case, $doc, $signerName, $signedAt, $mode, $ip, $ua, $signatureRel, $appUrl);
            $htmlRel = $this->storeBinary('cases/' . $caseId, $html, 'html', 'regulation-signature');
            $this->repo->addCaseAttachment(
                $caseId,
                'regulation_signature',
                'Constancia HTML — ' . $signerName,
                $htmlRel,
                $userId
            );
        } catch (\Throwable) {
        }

        return [
            'pdf_path' => $pdfRel,
            'signature_path' => $signatureRel,
            'mode' => $mode,
        ];
    }

    /** @param array<string,mixed> $case @param array<string,mixed>|null $doc */
    private function buildSignedPdf(
        int $caseId,
        array $case,
        ?array $doc,
        string $signerName,
        string $signedAt,
        string $mode,
        string $ip,
        string $appUrl,
        ?string $signatureAbs
    ): string {
        $full = trim(
            (string) ($case['student_name'] ?? '') . ' '
            . (string) ($case['student_last_name_p'] ?? '') . ' '
            . (string) ($case['student_last_name_m'] ?? '')
        );
        $studentName = $full !== '' ? $full : $signerName;

        $lines = [
            'Hoja de firma digital',
            '',
            'Esta hoja incluye la firma digital del alumno quien declara haber leido',
            'el reglamento completo y aceptar sus terminos para presentar la',
            'certificacion indicada.',
            '',
            'Certificacion: ' . (string) ($case['certification_name'] ?? '')
                . ' (' . (string) ($case['certification_code'] ?? '') . ')',
            'Proveedor: ' . (string) ($case['provider_name'] ?? ''),
            'Alumno: ' . $studentName,
            'Correo: ' . (string) ($case['student_email'] ?? ''),
            'Firmado como: ' . $signerName,
            'Fecha/hora: ' . $signedAt . ' (America/Mexico_City)',
            'IP: ' . ($ip !== '' ? $ip : '—'),
            'Portal: ' . $appUrl,
            '',
            '--- Firma del alumno ---',
        ];
        if ($mode === 'type') {
            $lines[] = '';
            $lines[] = $signerName;
            $lines[] = '(firma tipografica)';
        }

        $writer = new SimplePdfWriter($lines);
        if ($signatureAbs) {
            $writer->setPngImage($signatureAbs, 320, 100);
        }

        $stampRel = 'uploads/cases/' . $caseId . '/regulation-signature-page-' . date('YmdHis') . '.pdf';
        $stampAbs = dirname(__DIR__, 2) . '/storage/' . $stampRel;
        $writer->write($stampAbs);

        $finalRel = 'uploads/cases/' . $caseId . '/regulation-signed-' . date('YmdHis') . '.pdf';
        $finalAbs = dirname(__DIR__, 2) . '/storage/' . $finalRel;

        $originalAbs = null;
        $originalRel = trim((string) ($doc['file_path'] ?? ''));
        if ($originalRel !== '') {
            $candidate = Uploader::absolutePath($originalRel);
            if ($candidate !== null && PdfDocumentMerger::isPdfFile($candidate)) {
                $originalAbs = $candidate;
            }
        }

        if ($originalAbs !== null) {
            try {
                PdfDocumentMerger::mergeToFile([$originalAbs, $stampAbs], $finalAbs);
                @unlink($stampAbs);

                return $finalRel;
            } catch (\Throwable $e) {
                error_log('[PDV] No se pudo anexar hoja de firma al reglamento: ' . $e->getMessage());
                // Fallback: solo la hoja de firma
            }
        }

        // Sin PDF original usable: entregar la hoja de firma como evidencia.
        if (!@rename($stampAbs, $finalAbs)) {
            if (!@copy($stampAbs, $finalAbs)) {
                throw new \RuntimeException('No se pudo guardar el PDF firmado.');
            }
            @unlink($stampAbs);
        }

        return $finalRel;
    }

    /** @param array<string,mixed> $case @param array<string,mixed>|null $doc */
    private function buildHtmlReceipt(
        int $caseId,
        array $case,
        ?array $doc,
        string $signerName,
        string $signedAt,
        string $mode,
        string $ip,
        string $ua,
        ?string $signatureRel,
        string $appUrl
    ): string {
        $full = trim(
            (string) ($case['student_name'] ?? '') . ' '
            . (string) ($case['student_last_name_p'] ?? '') . ' '
            . (string) ($case['student_last_name_m'] ?? '')
        );
        $studentName = $full !== '' ? $full : $signerName;
        $img = '';
        if ($signatureRel) {
            $img = '<p><img src="/media?f=' . htmlspecialchars(rawurlencode($signatureRel), ENT_QUOTES, 'UTF-8')
                . '" alt="Firma" style="max-width:320px;border:1px solid #ccc;background:#fff"></p>';
        } elseif ($mode === 'type') {
            $img = '<p style="font-family:cursive;font-size:1.4rem">'
                . htmlspecialchars($signerName, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Hoja de firma</title></head><body>'
            . '<h1>Hoja de firma digital</h1>'
            . '<p>Esta hoja incluye la firma digital del alumno quien declara haber leído el reglamento completo '
            . 'y aceptar sus términos para presentar la certificación indicada.</p>'
            . '<p><strong>Certificación:</strong> ' . htmlspecialchars((string) ($case['certification_name'] ?? ''), ENT_QUOTES, 'UTF-8')
            . ' (' . htmlspecialchars((string) ($case['certification_code'] ?? ''), ENT_QUOTES, 'UTF-8') . ')</p>'
            . '<p><strong>Proveedor:</strong> ' . htmlspecialchars((string) ($case['provider_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Alumno:</strong> ' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Correo:</strong> ' . htmlspecialchars((string) ($case['student_email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Firmado como:</strong> ' . htmlspecialchars($signerName, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Fecha/hora:</strong> ' . htmlspecialchars($signedAt, ENT_QUOTES, 'UTF-8') . ' (America/Mexico_City)</p>'
            . '<p><strong>IP:</strong> ' . htmlspecialchars($ip !== '' ? $ip : '—', ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Portal:</strong> ' . htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Documento:</strong> ' . htmlspecialchars((string) ($doc['title'] ?? 'Reglamento'), ENT_QUOTES, 'UTF-8')
            . ' v' . htmlspecialchars((string) ($doc['version'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><em>Caso #' . (int) $caseId . ' · modo ' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '</em></p>'
            . $img
            . '</body></html>';
    }

    private function dataUrlToPngBytes(?string $dataUrl): ?string
    {
        if ($dataUrl === null || $dataUrl === '') {
            return null;
        }
        if (!preg_match('#^data:image/(png|jpeg|jpg|webp);base64,([A-Za-z0-9+/=\s]+)$#i', trim($dataUrl), $m)) {
            return null;
        }
        $bin = base64_decode(preg_replace('/\s+/', '', $m[2]) ?? '', true);
        if ($bin === false || strlen($bin) < 80) {
            return null;
        }
        if (strlen($bin) > 2_000_000) {
            throw new \RuntimeException('La imagen de firma es demasiado grande. Vuelve a dibujarla.');
        }

        $fmt = strtolower($m[1]);
        if ($fmt === 'png' && str_starts_with($bin, "\x89PNG")) {
            return $bin;
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            return str_starts_with($bin, "\x89PNG") ? $bin : null;
        }
        $img = @imagecreatefromstring($bin);
        if ($img === false) {
            return null;
        }
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($img);
        }
        if (function_exists('imagealphablending')) {
            imagealphablending($img, true);
        }
        if (function_exists('imagesavealpha')) {
            imagesavealpha($img, true);
        }
        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);
        $png = ob_get_clean();

        return is_string($png) && str_starts_with($png, "\x89PNG") ? $png : null;
    }

    private function storeBinary(string $subdir, string $contents, string $ext, string $prefix = 'sig'): string
    {
        $subdir = trim($subdir, '/');
        $base = dirname(__DIR__, 2) . '/storage/uploads/' . $subdir;
        if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) {
            throw new \RuntimeException('No se pudo crear la carpeta de firmas.');
        }
        $name = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $abs = $base . '/' . $name;
        if (@file_put_contents($abs, $contents) === false) {
            throw new \RuntimeException('No se pudo guardar el archivo de firma.');
        }

        return 'uploads/' . $subdir . '/' . $name;
    }
}
