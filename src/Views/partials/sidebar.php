<?php

use App\View;

/**
 * Menu lateral.
 *
 * O front NAO decide acesso -- ele evita OFERECER o que sera negado. Os itens
 * administrativos so aparecem para papel Admin ou marca master, que e exatamente a
 * politica AdminOrMaster da API.
 *
 * Os itens de leads aparecem para todos, inclusive o Usuario comum: ali quem decide sao
 * os perfis, action a action, e nao existe endpoint que devolva as permissoes do PROPRIO
 * usuario. Adivinhar geraria menu inconsistente -- o 403, quando vier, e tratado na tela.
 *
 * @var string $pagina @var bool $administra
 */

$itens = [
    ['rota' => '/',      'pagina' => 'dashboard', 'icone' => 'bi-speedometer2', 'rotulo' => 'Dashboard'],
    ['rota' => '/leads', 'pagina' => 'leads',     'icone' => 'bi-people',       'rotulo' => 'Leads'],
];

$administrativos = [
    ['rota' => '/usuarios',   'pagina' => 'usuarios',   'icone' => 'bi-person-gear',   'rotulo' => 'Usuarios'],
    ['rota' => '/perfis',     'pagina' => 'perfis',     'icone' => 'bi-shield-lock',   'rotulo' => 'Perfis'],
    ['rota' => '/permissoes', 'pagina' => 'permissoes', 'icone' => 'bi-key',           'rotulo' => 'Permissoes'],
    ['rota' => '/logs',       'pagina' => 'logs',       'icone' => 'bi-journal-text',  'rotulo' => 'Registros de log'],
];
?>
<aside class="sidebar">
    <nav class="nav flex-column py-2">

        <?php foreach ($itens as $item) : ?>
            <a class="nav-link <?= $pagina === $item['pagina'] ? 'active' : '' ?>"
               href="<?= View::e($item['rota']) ?>"
               title="<?= View::e($item['rotulo']) ?>">
                <i class="bi <?= View::e($item['icone']) ?>"></i>
                <span class="rotulo"><?= View::e($item['rotulo']) ?></span>
            </a>
        <?php endforeach; ?>

        <?php if ($administra) : ?>
            <div class="sidebar-secao">Administracao</div>

            <?php foreach ($administrativos as $item) : ?>
                <a class="nav-link <?= $pagina === $item['pagina'] ? 'active' : '' ?>"
                   href="<?= View::e($item['rota']) ?>"
                   title="<?= View::e($item['rotulo']) ?>">
                    <i class="bi <?= View::e($item['icone']) ?>"></i>
                    <span class="rotulo"><?= View::e($item['rotulo']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

    </nav>
</aside>
