<?php

declare(strict_types=1);

namespace App\Documents;

use App\Catalog\CatalogRepository;
use App\Config\Env;
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
            $ua,
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
            'Reglamento firmado digitalmente — ' . $signerName,
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

        // Constancia HTML de respaldo
        try {
            $html = $this->buildHtmlReceipt($caseId, $case, $doc, $signerName, $signedAt, $mode, $ip, $ua, $signatureRel);
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
        string $ua,
        string $appUrl,
        ?string $signatureAbs
    ): string {
        $full = trim(
            (string) ($case['student_name'] ?? '') . ' '
            . (string) ($case['student_last_name_p'] ?? '') . ' '
            . (string) ($case['student_last_name_m'] ?? '')
        );
        $lines = [
            'INSTITUTO DOCEO — Constancia de firma digital',
            'BE DIFFERENT, BE BETTER!',
            '',
            'Documento de evidencia para el proveedor certificador.',
            'El alumno firmo digitalmente el reglamento sin imprimir ni escanear.',
            '',
            'Caso PDV: #' . $caseId,
            'Certificacion: ' . (string) ($case['certification_name'] ?? '') . ' (' . (string) ($case['certification_code'] ?? '') . ')',
            'Proveedor: ' . (string) ($case['provider_name'] ?? ''),
            'Alumno: ' . ($full !== '' ? $full : $signerName),
            'Correo: ' . (string) ($case['student_email'] ?? ''),
            'CURP: ' . (string) ($case['student_curp'] ?? '—'),
            '',
            'Reglamento: ' . (string) ($doc['title'] ?? 'Reglamento del examen'),
            'Codigo doc: ' . (string) ($doc['code'] ?? '—') . '  Version: ' . (string) ($doc['version'] ?? '—'),
            'Archivo original: ' . (string) ($doc['file_path'] ?? '—'),
            '',
            'Declaracion:',
            'Declaro haber leido el reglamento completo y aceptar sus terminos',
            'para presentar la certificacion indicada.',
            '',
            'Firmado como: ' . $signerName,
            'Modo de firma: ' . ($mode === 'draw' ? 'Firma dibujada en pantalla' : 'Nombre escrito (firma tipografica)'),
            'Fecha/hora: ' . $signedAt . ' (America/Mexico_City)',
            'IP: ' . ($ip !== '' ? $ip : '—'),
            'Navegador: ' . ($ua !== '' ? mb_substr($ua, 0, 90) : '—'),
            '',
            'Portal: ' . $appUrl,
            'Ver caso: ' . $appUrl . '/admin/cases/view?id=' . $caseId,
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
        $rel = 'uploads/cases/' . $caseId . '/regulation-signed-' . date('YmdHis') . '.pdf';
        $abs = dirname(__DIR__, 2) . '/storage/' . $rel;
        $writer->write($abs);

        return $rel;
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
        ?string $signatureRel
    ): string {
        $img = '';
        if ($signatureRel) {
            $img = '<p><img src="/media?f=' . htmlspecialchars(rawurlencode($signatureRel), ENT_QUOTES, 'UTF-8')
                . '" alt="Firma" style="max-width:320px;border:1px solid #ccc;background:#fff"></p>';
        }

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Firma de reglamento</title></head><body>'
            . '<h1>Constancia de firma digital de reglamento</h1>'
            . '<p><strong>Caso:</strong> #' . (int) $caseId . '</p>'
            . '<p><strong>Alumno:</strong> ' . htmlspecialchars((string) ($case['student_name'] ?? ''), ENT_QUOTES, 'UTF-8')
            . ' ' . htmlspecialchars((string) ($case['student_last_name_p'] ?? ''), ENT_QUOTES, 'UTF-8')
            . ' (' . htmlspecialchars((string) ($case['student_email'] ?? ''), ENT_QUOTES, 'UTF-8') . ')</p>'
            . '<p><strong>Certificación:</strong> ' . htmlspecialchars((string) ($case['certification_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Documento:</strong> ' . htmlspecialchars((string) ($doc['title'] ?? 'Reglamento'), ENT_QUOTES, 'UTF-8')
            . ' v' . htmlspecialchars((string) ($doc['version'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Firmado como:</strong> ' . htmlspecialchars($signerName, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Modo:</strong> ' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Fecha/hora:</strong> ' . htmlspecialchars($signedAt, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>IP:</strong> ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Navegador:</strong> ' . htmlspecialchars($ua, ENT_QUOTES, 'UTF-8') . '</p>'
            . $img
            . '<p>El alumno declaró haber leído y aceptado el reglamento antes de continuar.</p>'
            . '</body></html>';
    }

    private function dataUrlToPngBytes(?string $dataUrl): ?string
    {
        if ($dataUrl === null || $dataUrl === '') {
            return null;
        }
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', trim($dataUrl), $m)) {
            return null;
        }
        $bin = base64_decode(preg_replace('/\s+/', '', $m[1]) ?? '', true);
        if ($bin === false || strlen($bin) < 100 || !str_starts_with($bin, "\x89PNG")) {
            return null;
        }
        if (strlen($bin) > 2_000_000) {
            throw new \RuntimeException('La imagen de firma es demasiado grande. Vuelve a dibujarla.');
        }

        return $bin;
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
