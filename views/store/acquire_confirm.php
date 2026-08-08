<?php $item = $item ?? []; $user = $user ?? []; ?>
<section class="page-head">
    <div>
        <h1>Confirmar adquisición</h1>
        <p class="muted"><?= e($item['name']) ?> · <?= e($user['email'] ?? '') ?></p>
    </div>
</section>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<section class="note">
    <p>Se abrirá un caso de seguimiento con el protocolo de esta certificación (si está configurado).</p>
    <form method="post" action="/adquirir" class="actions">
        <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
        <input type="hidden" name="mode" value="confirm">
        <button class="btn" type="submit">Confirmar y abrir mi seguimiento</button>
        <a class="btn btn-ghost" href="/certificacion?slug=<?= e(rawurlencode($item['slug'])) ?>">Cancelar</a>
    </form>
</section>
