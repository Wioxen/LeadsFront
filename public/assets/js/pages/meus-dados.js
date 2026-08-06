/* Meus dados: o proprio usuario sobre si mesmo. */
(function ($) {
  'use strict';

  if ($('body').data('page') !== 'meus-dados') {
    return;
  }

  var $form = $('#form-meus-dados');
  var meuUuid = $('#m-foto').data('uuid') || '';

  // Estado que veio do servidor. Serve para decidir se um pedido DESLIGA o segundo fator --
  // comparar com o que esta na tela nao bastaria, porque a caixa muda enquanto se edita.
  var exigiaCodigo = false;
  var travado = false;

  function mostrarFoto(temFoto) {
    if (temFoto) {
      // O 't' derruba o cache: a URL nao muda quando a foto muda.
      $('#m-foto').attr('src', '/' + meuUuid + '.jpg?t=' + Date.now()).removeClass('d-none');
      $('#m-foto-vazia').addClass('d-none');
      $('#m-remover-foto').removeClass('d-none');
    } else {
      $('#m-foto').removeAttr('src').addClass('d-none');
      $('#m-foto-vazia').removeClass('d-none');
      $('#m-remover-foto').addClass('d-none');
    }
  }

  /** Mostra o campo de senha apenas quando o pedido de fato desliga a exigencia. */
  function ajustarAviso() {
    var marcado = $('#m-2fa').is(':checked');

    $('#m-aviso-2fa').toggleClass('d-none', !(exigiaCodigo && !marcado && !travado));

    if (marcado || travado) { $('#m-senha').val(''); }
  }

  function preencher(u) {
    $('#m-firstName').val(u.firstName || '');
    $('#m-lastName').val(u.lastName || '');
    $('#m-email').val(u.email || '');
    $('#m-phone').val(u.phone || '');
    $('#m-phoneWhats').prop('checked', !!u.phoneWhats);
    $('#m-2fa').prop('checked', !!u.twoFactorEnabled);

    exigiaCodigo = !!u.twoFactorEnabled;
    travado = !!u.twoFactorLocked;

    /*
     * Admin nao desliga o segundo fator. A caixa e desabilitada e a razao aparece na tela --
     * oferecer um controle que o servidor ignora e mentir para quem o usa.
     */
    $('#m-2fa').prop('disabled', travado);
    $('#m-2fa-travado').toggleClass('d-none', !travado);

    mostrarFoto(!!u.photo);
    ajustarAviso();
  }

  function carregar() {
    App.get('/api/meus-dados')
      .done(preencher)
      .fail(function (xhr) { App.tratarErro(xhr); });
  }

  $('#m-2fa').on('change', ajustarAviso);

  $form.on('submit', function (e) {
    e.preventDefault();

    var $botao = $('#m-salvar');

    App.limparErros($form);
    App.ocupar($botao, true);

    App.put('/api/meus-dados', App.dadosDoFormulario($form))
      .done(function (u) {
        preencher(u);
        App.alerta('ok', 'Dados atualizados.');
      })
      .fail(function (xhr) { App.tratarErro(xhr, $form); })
      .always(function () { App.ocupar($botao, false); });
  });

  /* --- Foto --------------------------------------------------------------------------- */

  // Envia ao escolher, como na tela de usuarios: o registro ja existe, entao nao ha motivo
  // para segurar o arquivo no navegador ate alguem clicar em salvar.
  $('#m-arquivo').on('change', function () {
    var arquivo = this.files && this.files[0];

    if (!arquivo) { return; }

    var $rotulo = $('label[for="m-arquivo"]');
    var dados = new FormData();

    dados.append('file', arquivo);
    App.ocupar($rotulo, true);

    $.ajax({
      url: '/api/meus-dados/foto',
      method: 'POST',
      data: dados,

      // Obrigatorias com FormData: sem elas o jQuery serializa como texto e define um
      // Content-Type sem o boundary, e o arquivo nao chega.
      processData: false,
      contentType: false,
      headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') || '' }
    })
      .done(function () {
        mostrarFoto(true);
        App.alerta('ok', 'Foto atualizada.');
      })
      .fail(function (xhr) {
        // Limpa o campo para a pessoa poder repetir com outro arquivo.
        $('#m-arquivo').val('');
        App.tratarErro(xhr);
      })
      .always(function () { App.ocupar($rotulo, false); });
  });

  $('#m-remover-foto').on('click', function () {
    var $botao = $(this);

    App.ocupar($botao, true);

    App.del('/api/meus-dados/foto')
      .done(function () {
        $('#m-arquivo').val('');
        mostrarFoto(false);
        App.alerta('ok', 'Foto removida.');
      })
      .fail(function (xhr) { App.tratarErro(xhr); })
      .always(function () { App.ocupar($botao, false); });
  });

  carregar();
})(jQuery);
