<?php
require __DIR__ . '/../_nav.php';
$item = $item ?? null;
$handlers = $handlers ?? \App\Workflow\ActionRepository::handlers();
$triggerOptions = $triggerOptions ?? \App\Workflow\ActionRepository::triggerOptions();
$requireOptions = $requireOptions ?? \App\Workflow\ActionRepository::requireOptions();
$mail_templates = $mail_templates ?? [];

$selectedTriggers = [];
$rawT = $item['auto_triggers'] ?? null;
if (is_string($rawT) && $rawT !== '') {
    $decoded = json_decode($rawT, true);
    $selectedTriggers = is_array($decoded) ? $decoded : [];
} elseif (is_array($rawT)) {
    $selectedTriggers = $rawT;
}

$selectedRequires = [];
$rawR = $item['requires_json'] ?? null;
if (is_string($rawR) && $rawR !== '') {
    $decoded = json_decode($rawR, true);
    $selectedRequires = is_array($decoded) ? $decoded : [];
} elseif (is_array($rawR)) {
    $selectedRequires = $rawR;
}

$tab = $_GET['tab'] ?? 'general';
$tabs = [
    'general' => 'General',
    'automatizacion' => 'Automatización',
];
if (!isset($tabs[$tab])) {
    $tab = 'general';
}
?>
<section class="admin-ficha" data-admin-ficha data-tab="<?= e($tab) ?>">
<?php
$fichaTitle = $item['name'] ?? 'Nueva acción';
$fichaSubtitle = $item ? ($item['code'] ?? null) : null;
$fichaBackUrl = '/admin/actions';
$fichaMode = 'js';
$fichaTabBase = '';
require __DIR__ . '/../_ficha_head.php';
?>

    <form method="post" action="/admin/actions/save" class="stack form-grid">
        <?php if ($item): ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><?php endif; ?>

        <div class="admin-ficha-panel" data-tab-panel="general" <?= $tab !== 'general' ? 'hidden' : '' ?>>
            <label>Código
                <input name="code" required pattern="[A-Za-z0-9_]+" value="<?= e($item['code'] ?? '') ?>"
                       <?= $item ? 'readonly' : '' ?> placeholder="send_student_access">
            </label>
            <label>Nombre<input name="name" required value="<?= e($item['name'] ?? '') ?>"></label>
            <label>Etiqueta del botón
                <input name="button_label" value="<?= e($item['button_label'] ?? '') ?>" placeholder="Enviar accesos">
            </label>
            <label>Handler (qué hace)
                <select name="handler" required>
                    <?php foreach ($handlers as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= ($item['handler'] ?? 'send_mail') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Plantilla de correo (si aplica)
                <select name="mail_template_code">
                    <option value="">—</option>
                    <?php foreach ($mail_templates as $tpl): ?>
                        <option value="<?= e($tpl['code']) ?>" <?= ($item['mail_template_code'] ?? '') === $tpl['code'] ? 'selected' : '' ?>>
                            <?= e($tpl['name']) ?> (<?= e($tpl['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="muted">Obligatoria para “Enviar plantilla”. En “Enviar accesos” se usa esta o la del protocolo.</small>
            </label>
            <label class="field-wide">Descripción
                <textarea name="description" rows="2"><?= e($item['description'] ?? '') ?></textarea>
            </label>
            <label class="check field-wide">
                <input type="checkbox" name="show_as_button" <?= !isset($item) || !empty($item['show_as_button']) ? 'checked' : '' ?>>
                Mostrar como botón en la tabla de Casos
            </label>
            <label>Orden<input type="number" name="sort_order" value="<?= (int)($item['sort_order'] ?? 0) ?>"></label>
            <label class="check"><input type="checkbox" name="is_active" <?= !isset($item) || (int)($item['is_active'] ?? 1) ? 'checked' : '' ?>> Activa</label>
        </div>

        <div class="admin-ficha-panel" data-tab-panel="automatizacion" <?= $tab !== 'automatizacion' ? 'hidden' : '' ?>>
            <fieldset class="field-wide">
                <legend>Triggers automáticos</legend>
                <?php foreach ($triggerOptions as $code => $label): ?>
                    <label class="check">
                        <input type="checkbox" name="auto_triggers[]" value="<?= e($code) ?>"
                            <?= in_array($code, $selectedTriggers, true) ? 'checked' : '' ?>>
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="field-wide">
                <legend>Requisitos antes de ejecutar</legend>
                <?php foreach ($requireOptions as $code => $label): ?>
                    <label class="check">
                        <input type="checkbox" name="requires_json[]" value="<?= e($code) ?>"
                            <?= in_array($code, $selectedRequires, true) ? 'checked' : '' ?>>
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        </div>

        <div class="admin-ficha-actions">
            <button class="btn" type="submit">Guardar</button>
        </div>
    </form>
</section>
