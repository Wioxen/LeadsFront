/* Perfis: CRUD e concessao de permissoes agrupadas por recurso. */
(function ($) {
  'use strict';

  if ($('body').data('page') !== 'perfis') {
    return;
  }

  var tabela = null;
  var modal = null;
  var catalogo = [];

  // Ids INATIVOS que o perfil ja possuia. Guardados para serem reenviados: a API os
  // preserva de proposito, e omiti-los no PUT os removeria em silencio, so porque a
  // action correspondente saiu do codigo num refactor do servidor.
  var inativosPreservados = [];

  function montarTabela(dados) {
    $('#area-tabela').addClass('d-none');
    $('#tabela-perfis').removeClass('d-none');

    if (tabela) {
      tabela.destroy();
      $('#tabela-perfis tbody').empty();
    }

    tabela = App.tabela($('#tabela-perfis'), {
      data: dados.map(function (p) {
        var sistema = p.isSystem
          ? ' <span class="badge-suave info" title="Criado pela aplicacao">sistema</span>'
          : '';

        // Perfil de sistema nao pode ser excluido nem desativado: a API responde 409.
        // Desabilitar o botao evita o usuario descobrir isso pelo erro.
        var excluir = p.isSystem
          ? '<button class="btn btn-sm btn-outline-secondary" disabled ' +
            'title="Perfil de sistema nao pode ser excluido"><i class="fa-solid fa-trash-can"></i></button>'
          : '<button class="btn btn-sm btn-outline-danger btn-excluir" data-uuid="' +
            App.escapar(p.uuid) + '" data-nome="' + App.escapar(p.name) +
            '" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>';

        return [
          App.escapar(p.name) + sistema,
          App.escapar(p.description || ''),
          '<span class="num">' + ((p.permissions || []).length) + '</span>',
          p.status === 1
            ? '<span class="badge-suave ok">Ativo</span>'
            : '<span class="badge-suave neutro">Inativo</span>',
          '<div class="acoes-linha">' +
            '<button class="btn btn-sm btn-outline-secondary btn-editar" data-uuid="' +
              App.escapar(p.uuid) + '" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button> ' +
            excluir +
          '</div>'
        ];
      }),
      language: { emptyTable: 'Nenhum perfil cadastrado.' }
    });
  }

  function carregar() {
    App.get('/api/perfis')
      .done(function (r) { montarTabela(r.dados || []); })
      .fail(function (xhr) {
        if (xhr.status === 401) { return; }
        var p = App.problema(xhr);
        $('#area-tabela').html('<div class="estado-vazio"><i class="fa-solid fa-triangle-exclamation"></i>' +
          App.escapar(p.detail || p.title) + '</div>');
      });
  }

  /**
   * Agrupa por controllerDescription e lista as actions. Usa o displayName que a API ja
   * devolve concatenado -- concatenar no cliente faria cada tela escolher um separador
   * diferente.
   */
  function renderPermissoes(concedidas) {
    var grupos = {};

    catalogo.forEach(function (p) {
      var grupo = p.controllerDescription || p.controller;
      (grupos[grupo] = grupos[grupo] || []).push(p);
    });

    var html = Object.keys(grupos).sort().map(function (grupo) {
      var itens = grupos[grupo].map(function (p) {
        var concedida = concedidas.indexOf(p.id) >= 0;

        // Permissao inativa corresponde a uma action que saiu do codigo. No SELETOR ela
        // nao aparece -- conceder responderia 404. Ja concedida, aparece em cinza com
        // aviso: se sumisse da tela, o proximo PUT a removeria sem ninguem perceber.
        if (!p.isActive && !concedida) {
          return '';
        }

        return '<div class="form-check">' +
          '<input class="form-check-input perm" type="checkbox" value="' + p.id +
            '" id="perm-' + p.id + '"' + (concedida ? ' checked' : '') +
            (!p.isActive ? ' disabled' : '') + '>' +
          '<label class="form-check-label small" for="perm-' + p.id + '"' +
            (!p.isActive ? ' style="color:var(--text-muted)"' : '') + '>' +
            App.escapar(p.actionDescription || p.action) +
            (!p.isActive
              ? ' <span class="badge-suave neutro" title="A action nao existe mais no codigo">inativa</span>'
              : '') +
          '</label></div>';
      }).join('');

      if (!itens) {
        return '';
      }

      return '<div class="mb-3"><div class="fw-medium small mb-1">' +
        App.escapar(grupo) + '</div>' + itens + '</div>';
    }).join('');

    $('#lista-permissoes').html(html || '<p class="small mb-0">Nenhuma permissao no catalogo.</p>');
  }

  function abrirModal(uuid) {
    var $form = $('#form-perfil');

    App.limparErros($form);
    $form[0].reset();
    inativosPreservados = [];

    $('[name="uuid"]', $form).val(uuid || '');
    $('#modal-titulo').text(uuid ? 'Editar perfil' : 'Novo perfil');
    $('#lista-permissoes').html('<div class="skeleton" style="height:2rem"></div>');

    modal.show();

    // A tela de perfil pede as ocultas tambem: o perfil precisa exibir tudo o que concede,
    // inclusive o que foi ocultado do seletor.
    var pedidos = [App.get('/api/permissoes?incluirOcultas=true')];

    if (uuid) {
      pedidos.push(App.get('/api/perfis/' + uuid));
    }

    $.when.apply($, pedidos).done(function (respPerm, respPerfil) {
      catalogo = (pedidos.length === 1 ? respPerm : respPerm[0]).dados || [];

      var concedidas = [];

      if (uuid) {
        var perfil = respPerfil[0];

        $('[name="name"]', $form).val(perfil.name);
        $('[name="description"]', $form).val(perfil.description);
        $('[name="status"]', $form).val(perfil.status);

        concedidas = (perfil.permissions || []).map(function (p) { return p.id; });

        inativosPreservados = (perfil.permissions || [])
          .filter(function (p) { return !p.isActive; })
          .map(function (p) { return p.id; });
      }

      renderPermissoes(concedidas);
    });
  }

  $('#btn-novo').on('click', function () { abrirModal(null); });

  $('#tabela-perfis').on('click', '.btn-editar', function () {
    abrirModal($(this).data('uuid'));
  });

  $('#tabela-perfis').on('click', '.btn-excluir', function () {
    var uuid = $(this).data('uuid');

    App.confirmarExclusao('o perfil ' + $(this).data('nome')).then(function (ok) {
      if (!ok) { return; }

      App.del('/api/perfis/' + uuid)
        .done(function () { App.alerta('ok', 'Perfil excluido.'); carregar(); })
        .fail(function (xhr) { App.tratarErro(xhr); });
    });
  });

  $('#form-perfil').on('submit', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $botao = $('#btn-salvar');

    App.limparErros($form);

    var marcadas = $('#lista-permissoes .perm:checked').map(function () {
      return parseInt(this.value, 10);
    }).get();

    // Reenvia as inativas preservadas: elas estao desabilitadas na tela, entao nao entram
    // por ':checked', e omiti-las as removeria.
    inativosPreservados.forEach(function (id) {
      if (marcadas.indexOf(id) < 0) {
        marcadas.push(id);
      }
    });

    var dados = App.dadosDoFormulario($form);
    var uuid = dados.uuid;

    delete dados.uuid;
    dados.permissionIds = marcadas;

    App.ocupar($botao, true);

    (uuid ? App.put('/api/perfis/' + uuid, dados) : App.post('/api/perfis', dados))
      .done(function () {
        modal.hide();
        App.alerta('ok', uuid ? 'Perfil atualizado.' : 'Perfil criado.');
        carregar();
      })
      .fail(function (xhr) { App.tratarErro(xhr, $form); })
      .always(function () { App.ocupar($botao, false); });
  });

  $(function () {
    modal = new bootstrap.Modal(document.getElementById('modal-perfil'));
    carregar();
  });
})(jQuery);
