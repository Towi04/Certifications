<?php
require __DIR__ . '/../_nav.php';
$items = $items ?? [];
$case_buttons = $case_buttons ?? [];
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">Casos</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Una fila por alumno. Usa los botones de acción del protocolo (pago, solicitar examen, enviar accesos…).
                El detalle completo sigue en “Ver”.
            </p>
        </div>
        <a class="btn" href="/admin/cases/create">Abrir caso</a>
    </div>
    <?php if (!empty($info)): ?><p class="alert alert-ok"><?= e($info) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
    <div class="table-wrap" style="margin-top:1rem">
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Alumno</th>
                <th>Certificación</th>
                <th>Examen</th>
                <th>Pago</th>
                <th>Acceso</th>
                <th>Acciones</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="8" class="muted">No hay casos.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <?php
                $cid = (int) $item['id'];
                $buttons = $case_buttons[$cid] ?? [];
                $paid = !empty($item['payment_confirmed_at']);
                $folio = trim((string) ($item['folio_id'] ?? ''));
                $clave = trim((string) ($item['access_key'] ?? ''));
                $share = trim((string) ($item['payment_proof_share_token'] ?? ''));
                ?>
                <tr>
                    <td>#<?= $cid ?></td>
                    <td>
                        <strong><?= e($item['student_name']) ?></strong><br>
                        <span class="muted"><?= e($item['student_email']) ?></span>
                    </td>
                    <td><?= e($item['certification_code'] ?? '') ?><br><span class="muted"><?= e($item['certification_name'] ?? '') ?></span></td>
                    <td><?= e($item['exam_date'] ?? '—') ?><?php if (!empty($item['exam_time'])): ?><br><span class="muted"><?= e($item['exam_time']) ?></span><?php endif; ?></td>
                    <td>
                        <?= $paid ? '<span class="pill pill-ok">Pagado</span>' : '<span class="muted">Pendiente</span>' ?>
                        <?php if ($share !== ''): ?>
                            <br><a href="/c/<?= e(rawurlencode($share)) ?>" target="_blank" rel="noopener">link comprobante</a>
                        <?php elseif (!empty($item['payment_proof_path'])): ?>
                            <br><span class="muted">comprobante sin link</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($folio !== '' || $clave !== ''): ?>
                            <code><?= e($folio !== '' ? $folio : '—') ?></code>
                            <?php if ($clave !== ''): ?> / <code><?= e($clave) ?></code><?php endif; ?>
                        <?php elseif (!empty($item['moodle_user'])): ?>
                            Moodle: <code><?= e($item['moodle_user']) ?></code>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($buttons): ?>
                            <div class="actions" style="flex-wrap:wrap;gap:0.35rem">
                                <?php foreach ($buttons as $btn): ?>
                                    <?php if (!empty($btn['enabled'])): ?>
                                        <?php if (!empty($btn['needs_payment_file'])): ?>
                                            <form method="post" action="/admin/cases/run-action" enctype="multipart/form-data" class="inline-form" style="display:inline-flex;gap:0.25rem;align-items:center">
                                                <input type="hidden" name="case_id" value="<?= $cid ?>">
                                                <input type="hidden" name="action_id" value="<?= (int)$btn['id'] ?>">
                                                <input type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp" style="max-width:8rem;font-size:0.75rem" title="Comprobante opcional">
                                                <button class="btn" type="submit" style="padding:0.35rem 0.6rem;font-size:0.85rem"><?= e($btn['label']) ?></button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="/admin/cases/run-action" class="inline-form">
                                                <input type="hidden" name="case_id" value="<?= $cid ?>">
                                                <input type="hidden" name="action_id" value="<?= (int)$btn['id'] ?>">
                                                <button class="btn" type="submit" style="padding:0.35rem 0.6rem;font-size:0.85rem"><?= e($btn['label']) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-ghost" type="button" disabled title="<?= e(implode(', ', $btn['blocked_by'] ?? [])) ?>" style="padding:0.35rem 0.6rem;font-size:0.85rem"><?= e($btn['label']) ?></button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="muted">Sin acciones en el protocolo</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="/admin/cases/view?id=<?= $cid ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
