<?php
require __DIR__ . '/../_nav.php';
$items = $items ?? [];
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Reglamentos firmados</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Evidencia de que el alumno leyó y aceptó el reglamento (nombre, fecha y documento).
            </p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha firma</th>
                    <th>Alumno</th>
                    <th>Firmó como</th>
                    <th>Certificación</th>
                    <th>Documento</th>
                    <th>PDF firmado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                $full = trim(
                    ($item['student_name'] ?? '') . ' '
                    . ($item['student_last_name_p'] ?? '') . ' '
                    . ($item['student_last_name_m'] ?? '')
                );
                ?>
                <tr>
                    <td><?= e((string)($item['regulation_signed_at'] ?? '')) ?></td>
                    <td>
                        <?= e($full) ?>
                        <br><span class="muted"><?= e((string)($item['student_email'] ?? '')) ?></span>
                    </td>
                    <td><?= e((string)($item['regulation_signer_name'] ?? '')) ?></td>
                    <td>
                        <?= e((string)($item['certification_name'] ?? '')) ?>
                        <br><code><?= e((string)($item['certification_code'] ?? '')) ?></code>
                    </td>
                    <td>
                        <?php if (!empty($item['document_title'])): ?>
                            <?= e($item['document_title']) ?>
                            <?php if (!empty($item['document_version'])): ?>
                                <span class="muted">v<?= e($item['document_version']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['document_path'])): ?>
                                <br><a href="/media?f=<?= e(rawurlencode((string)$item['document_path'])) ?>" target="_blank" rel="noopener">original</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted">Documento no disponible</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($item['regulation_signed_pdf_path'])): ?>
                            <a class="btn btn-ghost" href="/media?f=<?= e(rawurlencode((string)$item['regulation_signed_pdf_path'])) ?>" target="_blank" rel="noopener">Descargar</a>
                            <?php if (!empty($item['regulation_signature_mode'])): ?>
                                <br><span class="muted"><?= e($item['regulation_signature_mode'] === 'draw' ? 'dibujo' : 'nombre') ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted">Solo clickwrap (antes de firma digital)</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="/admin/cases/view?id=<?= (int)$item['case_id'] ?>">Caso #<?= (int)$item['case_id'] ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="7" class="muted">Aún no hay firmas registradas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
