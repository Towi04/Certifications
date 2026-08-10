<?php
require __DIR__ . '/_nav.php';
$webhookUrl = $webhookUrl ?? '';
$webhooks = $webhooks ?? [];
$matched = $matched ?? null;
$events = $events ?? [];
$verification = $verification ?? null;
$sandbox = !empty($sandbox);
$dashboardHost = $sandbox ? 'https://sandbox-dashboard.openpay.mx' : 'https://dashboard.openpay.mx';
?>
<section class="note">
    <div class="page-head" style="margin:0">
        <div>
            <h2 style="margin:0">OpenPay · Webhook</h2>
            <p class="muted" style="margin:0.35rem 0 0">
                Registra la URL del PDV para que OpenPay confirme pagos SPEI automáticamente
                (<?= $sandbox ? 'sandbox' : 'producción' ?> · merchant <?= e((string)($merchantId ?? '—')) ?>).
            </p>
        </div>
        <div class="actions">
            <a class="btn btn-ghost" href="/admin/salud">Salud</a>
            <a class="btn btn-ghost" href="<?= e($dashboardHost) ?>" target="_blank" rel="noopener">Dashboard OpenPay</a>
        </div>
    </div>

    <?php if (!empty($listError)): ?><p class="alert alert-error">No se pudo listar webhooks: <?= e($listError) ?></p><?php endif; ?>

    <h3>Requisitos OpenPay para producción</h3>
    <p class="muted">
        El PDV cobra por <strong>SPEI / CLABE</strong> (sin capturar tarjetas en tus servidores).
        Eso cubre PCI DSS para el flujo actual: los datos de tarjeta no pasan por el PDV.
    </p>
    <ul>
        <li><strong>Productos/servicios:</strong> la vitrina pública y fichas de certificación muestran qué se paga.</li>
        <li><strong>SSL válido:</strong> el sitio debe servir en HTTPS (Neubox / certificado del dominio). OpenPay exige webhook HTTPS.</li>
        <li><strong>Antifraudes OpenPay:</strong> aplica sobre todo a cargos con <em>tarjeta</em> (device session / fraud tools).
            Con SPEI el riesgo es distinto; no integramos cobro con tarjeta todavía. Si más adelante activas tarjeta,
            habrá que integrar la librería JS/móvil de OpenPay y el antifraude.</li>
        <li><strong>Librería web/móvil PCI:</strong> no aplica mientras solo uses SPEI. No almacenes NI proceses PAN en el PDV.</li>
    </ul>
    <p class="muted">
        Checklist operativo: webhook verificado, <code>OPENPAY_*</code> de producción en <code>.env</code>,
        y modo sandbox desactivado. Revisa también <a href="/admin/salud">Salud</a>.
    </p>

    <h3>URL del webhook</h3>
    <p><code id="webhookUrl"><?= e($webhookUrl) ?></code>
        <button type="button" class="linkish" id="copyWebhookUrl">Copiar</button>
    </p>
    <p class="muted">
        Método: <code>POST</code>. Ping: <a href="<?= e($webhookUrl) ?>" target="_blank" rel="noopener"><code>GET</code></a>
        (debe responder JSON <code>ok</code>).
        <?php if (!empty($authUserConfigured)): ?>
            Auth Basic activa vía <code>OPENPAY_WEBHOOK_USER</code>.
        <?php else: ?>
            Sin auth Basic (opcional: define <code>OPENPAY_WEBHOOK_USER</code> / <code>OPENPAY_WEBHOOK_PASSWORD</code> en <code>.env</code>).
        <?php endif; ?>
    </p>

    <h3>Estado en OpenPay</h3>
    <?php if ($matched): ?>
        <p>
            <span class="pill <?= ($matched['status'] ?? '') === 'verified' ? 'pill-ok' : 'pill-muted' ?>">
                <?= e((string)($matched['status'] ?? 'desconocido')) ?>
            </span>
            ID <code><?= e((string)($matched['id'] ?? '')) ?></code>
        </p>
        <?php if (($matched['status'] ?? '') !== 'verified'): ?>
            <p class="muted">
                Si quedó <strong>unverified</strong>, copia el código de abajo y pégalo en el dashboard
                (Webhooks → Verificar), o elimina y vuelve a registrar desde aquí (la API suele verificar al crear si la URL responde 200).
            </p>
        <?php endif; ?>
        <form method="post" action="/admin/openpay/delete-webhook" class="inline-form"
              onsubmit="return confirm('¿Eliminar este webhook en OpenPay?');">
            <input type="hidden" name="webhook_id" value="<?= e((string)($matched['id'] ?? '')) ?>">
            <button type="submit" class="btn btn-ghost">Eliminar webhook</button>
        </form>
    <?php else: ?>
        <p><span class="pill pill-muted">No registrado</span> en esta cuenta OpenPay para la URL del PDV.</p>
        <form method="post" action="/admin/openpay/register-webhook" style="margin-top:0.75rem">
            <button class="btn" type="submit">Registrar webhook en OpenPay</button>
        </form>
        <p class="muted" style="margin-top:0.5rem">
            OpenPay hará un POST de verificación a la URL. Asegúrate de que
            <code><?= e($webhookUrl) ?></code> sea pública (HTTPS) antes de registrar.
        </p>
    <?php endif; ?>

    <?php if ($verification && !empty($verification['verification_code'])): ?>
        <h3>Código de verificación</h3>
        <p>
            Último recibido: <code><?= e((string)$verification['verification_code']) ?></code>
            <span class="muted"><?= e((string)($verification['created_at'] ?? '')) ?></span>
        </p>
        <p class="muted">Úsalo en el dashboard si el webhook quedó sin verificar.</p>
    <?php endif; ?>

    <?php if ($webhooks): ?>
        <h3>Webhooks en la cuenta</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>ID</th><th>URL</th><th>Estado</th><th>Eventos</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($webhooks as $wh): ?>
                    <tr>
                        <td><code><?= e((string)($wh['id'] ?? '')) ?></code></td>
                        <td><?= e((string)($wh['url'] ?? '')) ?></td>
                        <td><?= e((string)($wh['status'] ?? '—')) ?></td>
                        <td class="muted">
                            <?php
                            $types = $wh['event_types'] ?? [];
                            echo e(is_array($types) ? implode(', ', array_map('strval', $types)) : '—');
                            ?>
                        </td>
                        <td>
                            <form method="post" action="/admin/openpay/delete-webhook" class="inline-form"
                                  onsubmit="return confirm('¿Eliminar webhook?');">
                                <input type="hidden" name="webhook_id" value="<?= e((string)($wh['id'] ?? '')) ?>">
                                <button type="submit" class="linkish">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h3>Eventos recientes</h3>
    <?php if ($events): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Charge / Order</th>
                        <th>Caso</th>
                        <th>OK</th>
                        <th>Nota</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $ev): ?>
                    <tr>
                        <td><?= e((string)($ev['created_at'] ?? '')) ?></td>
                        <td><code><?= e((string)($ev['event_type'] ?? '')) ?></code></td>
                        <td>
                            <code><?= e((string)($ev['openpay_charge_id'] ?? '—')) ?></code>
                            <?php if (!empty($ev['order_id'])): ?>
                                <br><span class="muted"><?= e((string)$ev['order_id']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($ev['case_id'])): ?>
                                <a href="/admin/cases/view?id=<?= (int)$ev['case_id'] ?>">#<?= (int)$ev['case_id'] ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($ev['processed']) ? 'Sí' : 'No' ?></td>
                        <td class="muted"><?= e((string)($ev['error_message'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="muted">Aún no hay eventos recibidos. Tras registrar el webhook, OpenPay enviará al menos la verificación.</p>
    <?php endif; ?>

    <h3>Pasos manuales (dashboard)</h3>
    <ol>
        <li>Entra al <a href="<?= e($dashboardHost) ?>" target="_blank" rel="noopener">dashboard OpenPay</a> (sandbox o producción según tus llaves).</li>
        <li>Webhooks → Agregar → URL <code><?= e($webhookUrl) ?></code>.</li>
        <li>Eventos mínimos: <code>charge.succeeded</code>, <code>spei.received</code>.</li>
        <li>OpenPay envía un POST <code>verification</code>; el código aparece arriba (o en <code>storage/logs/openpay-webhook-verification.txt</code>).</li>
        <li>Verifica el webhook con ese código. Los pagos SPEI confirmados marcarán el caso como pagado y enviarán <code>pago_confirmado</code>.</li>
    </ol>
</section>
<script>
(() => {
  const btn = document.getElementById('copyWebhookUrl');
  const el = document.getElementById('webhookUrl');
  btn?.addEventListener('click', async () => {
    const text = el?.textContent || '';
    try {
      await navigator.clipboard.writeText(text);
      btn.textContent = 'Copiado';
      setTimeout(() => { btn.textContent = 'Copiar'; }, 1500);
    } catch (_) {
      btn.textContent = 'No se pudo copiar';
    }
  });
})();
</script>
