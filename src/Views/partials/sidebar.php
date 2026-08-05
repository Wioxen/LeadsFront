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
    ['rota' => '/logs',       'pagina' => 'logs',       'icone' => 'bi-journal-text',  'rotulo' => 'Registros de log'],
];

/*
 * Permissoes fica FORA da lista acima: a tela edita o catalogo, e isso e do Admin. O
 * master monta perfil com as permissoes que existem, mas nao muda quais existem nem como
 * se chamam -- o rotulo que ele escrevesse valeria para todos os outros.
 */
$somenteAdmin = [
    ['rota' => '/permissoes', 'pagina' => 'permissoes', 'icone' => 'bi-key', 'rotulo' => 'Permissoes'],
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

            <?php if ($papel === 'Admin') : ?>
                <?php foreach ($somenteAdmin as $item) : ?>
                    <a class="nav-link <?= $pagina === $item['pagina'] ? 'active' : '' ?>"
                       href="<?= View::e($item['rota']) ?>"
                       title="<?= View::e($item['rotulo']) ?>">
                        <i class="bi <?= View::e($item['icone']) ?>"></i>
                        <span class="rotulo"><?= View::e($item['rotulo']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

    </nav>
</aside>
