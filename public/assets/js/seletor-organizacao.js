/*
 * Seletor de organizacao da barra superior.
 *
 * Existe porque, com o modelo N:N, a mesma pessoa participa de varias organizacoes e trocar
 * exigia SAIR do sistema e entrar de novo -- com senha e, para quem administra, com codigo.
 * O incomodo levava ao habito pior: manter duas sessoes em navegadores diferentes.
 *
 * Carregado pelo layout 'app', junto da barra. Nao faz nada quando o seletor nao existe na
 * pagina, que e o caso das telas anonimas.
 */
(function ($) {
  'use strict';

  var $seletor = $('#seletor-org');

  if (!$seletor.length) { return; }

  var $lista = $('#lista-orgs');
  var carregada = false;

  /*
   * Carrega ao ABRIR, uma vez por pagina.
   *
   * Buscar no render custaria uma ida a API em toda navegacao, para uma informacao que quase
   * nunca muda e que quem participa de uma organizacao so nunca vai usar.
   */
  $seletor.on('show.bs.dropdown', function () {
    if (carregada) { return; }

    App.get('/api/organizacoes')
      .done(function (orgs) {
        carregada = true;
        desenhar(orgs || []);
      })
      .fail(function () {
        // Nao marca como carregada: a proxima abertura tenta de novo. Uma falha de rede nao
        // deve condenar o menu ao estado de erro pelo resto da pagina.
        $lista.html(
          '<li class="px-3 py-2 small text-danger">Nao foi possivel carregar.</li>'
        );
      });
  });

  function desenhar(orgs) {
    if (!orgs.length) {
      $lista.html('<li class="px-3 py-2 small">Nenhuma organizacao.</li>');
      return;
    }

    // Uma organizacao so: diz isso em vez de oferecer um menu de um item, que sugere uma
    // escolha que nao existe.
    if (orgs.length === 1) {
      $lista.html(
        '<li class="px-3 py-2 small" style="color: var(--text-secondary)">'
        + 'Voce participa apenas desta organizacao.</li>'
      );
      return;
    }

    var html = '<li class="px-3 pt-2 pb-1 small text-uppercase fw-semibold"'
             + ' style="color: var(--text-secondary); letter-spacing:.04em">Trocar para</li>';

    orgs.forEach(function (o) {
      var papel = o.roleType === 1 ? 'Administrador' : 'Usuario';

      if (o.atual) {
        html += '<li><span class="dropdown-item-text d-flex align-items-center gap-2">'
              + '<i class="fa-solid fa-check text-success"></i>'
              + '<span><span class="fw-semibold">' + App.escapar(o.name) + '</span>'
              + '<br><span class="small" style="color: var(--text-secondary)">'
              + papel + ' &middot; organizacao atual</span></span></span></li>';
        return;
      }

      /*
       * O papel aparece no menu de proposito: trocar para uma organizacao onde a pessoa e
       * Administrador vai PEDIR o codigo, e saber disso antes do clique evita a surpresa de
       * cair numa tela de segundo fator sem entender por que.
       */
      html += '<li><a class="dropdown-item d-flex align-items-center gap-2" href="#"'
            + ' data-uuid="' + App.escapar(o.uuid) + '">'
            + '<i class="fa-solid fa-arrow-right-arrow-left" style="color: var(--text-secondary)"></i>'
            + '<span><span class="fw-medium">' + App.escapar(o.name) + '</span>'
            + '<br><span class="small" style="color: var(--text-secondary)">' + papel + '</span>'
            + '</span></a></li>';
    });

    $lista.html(html);
  }

  $lista.on('click', 'a[data-uuid]', function (e) {
    e.preventDefault();

    var $item = $(this);

    // Trava o menu inteiro: um segundo clique durante a troca dispararia um pedido para outra
    // organizacao enquanto o primeiro ainda decide qual token vale.
    $lista.find('a[data-uuid]').addClass('disabled').css('pointer-events', 'none');
    $item.find('i').attr('class', 'fa-solid fa-spinner fa-spin');

    App.post('/api/organizacoes/trocar', { tenantUuid: $item.data('uuid') })
      .done(function (r) {
        /*
         * Recarrega em vez de atualizar a tela no lugar. O token novo muda TUDO o que a
         * pagina mostra -- lista, permissoes, menu --, e remendar cada parte deixaria pedaco
         * da organizacao anterior visivel.
         */
        window.location.href = r.destino || '/';
      })
      .fail(function (xhr) {
        $lista.find('a[data-uuid]').removeClass('disabled').css('pointer-events', '');
        $item.find('i').attr('class', 'fa-solid fa-arrow-right-arrow-left');

        var p = App.tratarErro(xhr);

        App.alerta('erro', p.detail || 'Nao foi possivel trocar de organizacao.');
      });
  });
})(jQuery);
