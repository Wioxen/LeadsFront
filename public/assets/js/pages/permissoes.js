/* Catalogo de permissoes: leitura, rotulos amigaveis e visibilidade. */
(function ($) {
  'use strict';

  if ($('body').data('page') !== 'permissoes') {
    return;
  }

  var tabela = null;
  var modal = null;

  function carregar() {
    var incluirOcultas = $('#chk-ocultas').is(':checked');

    App.get('/api/permissoes' + (incluirOcultas ? '?incluirOcultas=true' : ''))
      .done(function (r) { montarTabela(r.dados || []); })
      .fail(function (xhr) {
        if (xhr.status === 401) { return; }
        var p = App.problema(xhr);
        $('#area-tabela').html('<div class="estado-vazio"><i class="bi bi-exclamation-triangle"></i>' +
          App.escapar(p.detail || p.title) + '</div>');
      });
  }

  function montarTabela(dados) {
    $('#area-tabela').addClass('d-none');
    $('#tabela-permissoes').removeClass('d-none');

    if (tabela) {
      tabela.destroy();
      $('#tabela-permissoes tbody').empty();
    }

    tabela = App.tabela($('#tabela-permissoes'), {
      data: dados.map(function (p) {
        var situacao = !p.isActive
          // Inativa = a action saiu do codigo. Nao concede nada, e concede-la da 404.
          ? '<span class="badge-suave erro" title="A action nao existe mais no codigo">inativa</span>'
          : (p.isVisible
              ? '<span class="badge-suave ok">visivel</span>'
              : '<span class="badge-suave neutro" title="Fora do seletor de perfis">oculta</span>');

        return [
          App.escapar(p.controllerDescription || p.controller),
          App.escapar(p.actionDescription || p.action),
          '<code class="small">' + App.escapar(p.controller + '.' + p.action) + '</code>',
          situacao,
          '<div class="acoes-linha"><button class="btn btn-sm btn-outline-secondary btn-editar" ' +
            'data-permissao=\'' + App.escapar(JSON.stringify(p)) + '\' title="Editar rotulos">' +
            '<i class="bi bi-pencil"></i></button></div>'
        ];
      }),
      language: { emptyTable: 'Nenhuma permissao no catalogo.' }
    });
  }

  $('#chk-ocultas').on('change', carregar);

  $('#tabela-permissoes').on('click', '.btn-editar', function () {
    var p = $(this).data('permissao');
    var $form = $('#form-permissao');

    App.limparErros($form);

    $('[name="uuid"]', $form).val(p.uuid);
    $('#pm-tecnico').val(p.controller + '.' + p.action);
    $('#pm-controller').val(p.controllerDescription || '');
    $('#pm-action').val(p.actionDescription || '');
    $('#pm-visivel').prop('checked', !!p.isVisible);

    modal.show();
  });

  $('#form-permissao').on('submit', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $botao = $('#btn-salvar');
    var uuid = $('[name="uuid"]', $form).val();

    App.limparErros($form);
    App.ocupar($botao, true);

    App.put('/api/permissoes/' + uuid, {
      controllerDescription: $('#pm-controller').val(),
      actionDescription: $('#pm-action').val(),
      isVisible: $('#pm-visivel').is(':checked')
    })
      .done(function () {
        modal.hide();
        App.alerta('ok', 'Rotulos atualizados.');
        carregar();
      })
      .fail(function (xhr) { App.tratarErro(xhr, $form); })
      .always(function () { App.ocupar($botao, false); });
  });

  $(function () {
    modal = new bootstrap.Modal(document.getElementById('modal-permissao'));
    carregar();
  });
})(jQuery);
