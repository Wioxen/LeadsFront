<?php

use App\Auth\Session;
use App\View;

/** @var string $usuarioNome @var string $usuarioEmail @var string $papel @var bool $ehMaster */
?>
<nav class="navbar-app d-flex align-items-center px-3 gap-3">

    <button class="btn btn-sm border-0" id="btn-sidebar" type="button" aria-label="Alternar menu">
        <i class="fa-solid fa-bars fs-5"></i>
    </button>

    <a href="/" class="d-flex align-items-center gap-2 text-decoration-none">
        <i class="fa-solid fa-layer-group fs-5" style="color: var(--brand-primary)"></i>
        <span class="fw-semibold" style="color: var(--text-primary)">CRM</span>
    </a>

    <?php
    /*
     * Identificacao do TENANT, sempre visivel. Em produto multi-tenant, nao saber em qual
     * organizacao se esta e a origem do erro mais caro possivel -- cadastrar o lead certo
     * na empresa errada.
     *
     * Passou a valer MAIS com o modelo N:N: antes, quem entrava so podia estar numa
     * organizacao, e o rotulo era conferencia. Agora a mesma pessoa escolhe entre varias no
     * login, e "por que nao vejo o registro que criei ontem?" costuma ser exatamente estar na
     * outra -- uma pergunta que este rotulo responde antes de ser feita.
     *
     * O NOME vem da claim TenantName. Tokens emitidos antes de ela existir nao a tem, e ai
     * cai no identificador curto, que era o que se mostrava ate agora: pior de ler, mas
     * melhor que um cabecalho que some justo em quem ainda nao renovou a sessao.
     */
    $tenantUuid = Session::claim('TenantUuid');
    $tenantNome = Session::organizacao();
    ?>
    <?php if ($tenantUuid !== null) : ?>
        <?php $rotulo = $tenantNome !== null && $tenantNome !== '' ? $tenantNome : 'org ' . substr($tenantUuid, 0, 8); ?>
        <div class="dropdown d-none d-md-block" id="seletor-org">
            <button class="badge-suave neutro border-0 dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false"
                    title="Organizacao <?= View::e($tenantUuid) ?>">
                <i class="fa-solid fa-building me-1"></i><?= View::e($rotulo) ?>
            </button>
            <ul class="dropdown-menu shadow-sm" id="lista-orgs">
                <?php
                /*
                 * A lista carrega ao ABRIR, nao no render da pagina.
                 *
                 * Buscá-la em toda navegacao custaria uma ida a API por pagina para uma
                 * informacao que quase nunca muda e que a maioria das pessoas -- as de uma
                 * organizacao so -- nunca vai usar.
                 */
                ?>
                <li class="px-3 py-2 small" style="color: var(--text-secondary)">Carregando…</li>
            </ul>
        </div>
    <?php endif; ?>

    <div class="ms-auto d-flex align-items-center gap-2">

        <button class="btn btn-sm border-0" id="btn-tema" type="button" aria-label="Alternar tema">
            <i class="fa-solid fa-moon"></i>
        </button>

        <div class="dropdown">
            <button class="btn btn-sm border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <span class="rounded-circle d-grid place-items-center"
                      style="width:32px;height:32px;background:var(--brand-primary);color:#fff;font-weight:600;font-size:.8125rem;display:grid;place-items:center">
                    <?= View::e(mb_strtoupper(mb_substr($usuarioNome, 0, 1))) ?>
                </span>
                <span class="d-none d-md-inline" style="color: var(--text-primary)"><?= View::e($usuarioNome) ?></span>
                <i class="fa-solid fa-chevron-down small"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2">
                    <div class="fw-semibold small"><?= View::e($usuarioNome) ?></div>
                    <div class="small" style="color: var(--text-secondary)"><?= View::e($usuarioEmail) ?></div>
                    <div class="mt-1">
                        <span class="badge-suave <?= $papel === 'Admin' ? 'info' : 'neutro' ?>"><?= View::e($papel ?: 'Usuario') ?></span>
                        <?php if ($ehMaster) : ?>
                            <span class="badge-suave ok" title="Acesso livre, sem perfil vinculado">master</span>
                        <?php endif; ?>
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="/meus-dados">
                        <i class="fa-solid fa-id-card me-2"></i>Meus dados
                    </a>
                </li>
                <li>
                    <button type="button" class="dropdown-item" data-bs-toggle="modal"
                            data-bs-target="#modal-senha">
                        <i class="fa-solid fa-key me-2"></i>Trocar senha
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="/logout">
                        <i class="fa-solid fa-right-from-bracket me-2"></i>Sair
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
