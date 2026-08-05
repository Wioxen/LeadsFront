/* Usuarios: cadastro, edicao, exclusao e vinculo de perfis. */
(function ($) {
  'use strict';

  if ($('body').data('page') !== 'usuarios') {
    return;
  }

  var tabela = null;
  var modalUsuario = null;
  var modalPerfis = null;
  var perfisDisponiveis = [];

  var PAPEIS = { 1: 'Admin', 2: 'Usuario' };

  function montarTabela(dados) {
    $('#area-tabela').addClass('d-none');
    $('#tabela-usuarios').removeClass('d-none');

    if (tabela) {
      tabela.destroy();
      $('#tabela-usuarios tbody').empty();
    }

    var linhas = dados.map(function (u) {
      var nome = App.escapar((u.firstName || '') + ' ' + (u.lastName || ''));

      // Cor nunca e o unico sinal: o badge leva rotulo em texto.
      var papel = '<span class="badge-suave ' + (u.roleType === 1 ? 'info' : 'neutro') + '">' +
        App.escapar(PAPEIS[u.roleType] || u.roleType) + '</span>' +
        (u.master ? ' <span class="badge-suave ok" title="Acesso livre, sem perfil">master</span>' : '');

      var situacao = u.status === 1
        ? '<span class="badge-suave ok">Ativo</span>'
        : '<span class="badge-suave neutro">Inativo</span>';

      var verificado = u.verifiedAtUtc
        ? ''
        : ' <span class="badge-suave aviso" title="Ainda nao definiu a senha pelo link">pendente</span>';

      return [
        nome,
        App.escapar(u.email),
        papel,
        situacao + verificado,
        '<div class="text-end text-nowrap">' +
          '<button class="btn btn-sm btn-outline-secondary btn-perfis" data-uuid="' + App.escapar(u.uuid) +
            '" data-nome="' + nome + '" data-master="' + (u.master ? '1' : '') + '" title="Perfis">' +
            '<i class="bi bi-shield-lock"></i></button> ' +
          '<button class="btn btn-sm btn-outline-secondary btn-editar" data-usuario=\'' +
            App.escapar(JSON.stringify(u)) + '\' title="Editar"><i class="bi bi-pencil"></i></button> ' +
          '<button class="btn btn-sm btn-outline-danger btn-excluir" data-uuid="' + App.escapar(u.uuid) +
            '" data-nome="' + nome + '" title="Excluir"><i class="bi bi-trash"></i></button>' +
        '</div>'
      ];
    });

    tabela = $('#tabela-usuarios').DataTable({
      data: linhas,
      responsive: true,
      pageLength: 25,
      order: [],
      language: {
        emptyTable: 'Nenhum usuario cadastrado.',
        zeroRecords: 'Nenhum usuario encontrado.',
        info: 'Mostrando _START_ a _END_ de _TOTAL_',
        infoEmpty: 'Nenhum registro',
        lengthMenu: '_MENU_ por pagina',
        search: 'Buscar:',
        paginate: { first: 'Primeira', last: 'Ultima', next: 'Proxima', previous: 'Anterior' }
      }
    });
  }

  function carregar() {
    App.get('/api/usuarios')
      .done(function (r) { montarTabela(r.dados || []); })
      .fail(function (xhr) {
        if (xhr.status === 401) { return; }

        var p = App.problema(xhr);
        $('#area-tabela').html('<div class="estado-vazio"><i class="bi bi-exclamation-triangle"></i>' +
          App.escapar(p.detail || p.title) + '</div>');
      });
  }

  /* --- Formulario -------------------------------------------------------------------- */

  function abrirModal(u) {
    var $form = $('#form-usuario');

    App.limparErros($form);
    $form[0].reset();
    $('#aviso-master').addClass('d-none');

    $('[name="uuid"]', $form).val(u ? u.uuid : '');

    if (u) {
      $('[name="firstName"]', $form).val(u.firstName);
      $('[name="lastName"]', $form).val(u.lastName);
      $('[name="email"]', $form).val(u.email);
      $('[name="phone"]', $form).val(u.phone);
      $('[name="phoneWhats"]', $form).prop('checked', !!u.phoneWhats);
      $('[name="status"]', $form).val(u.status);
      $('[name="level"]', $form).val(u.level || 0);
      $('[name="master"]', $form).prop('checked', !!u.master);
    }

    $('#modal-titulo').text(u ? 'Editar usuario' : 'Novo usuario');

    modalUsuario.show();
  }

  $('#btn-novo').on('click', function () { abrirModal(null); });

  $('#tabela-usuarios').on('click', '.btn-editar', function () {
    abrirModal($(this).data('usuario'));
  });

  $('#tabela-usuarios').on('click', '.btn-excluir', function () {
    var uuid = $(this).data('uuid');

    App.confirmarExclusao('o usuario ' + $(this).data('nome')).then(function (ok) {
      if (!ok) { return; }

      App.del('/api/usuarios/' + uuid)
        .done(function () { App.alerta('ok', 'Usuario excluido.'); carregar(); })
        .fail(function (xhr) { App.tratarErro(xhr); });
    });
  });

  $('#form-usuario').on('submit', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $botao = $('#btn-salvar');

    App.limparErros($form);
    $('#aviso-master').addClass('d-none');

    var dados = App.dadosDoFormulario($form);
    var uuid = dados.uuid;

    delete dados.uuid;

    App.ocupar($botao, true);

    (uuid ? App.put('/api/usuarios/' + uuid, dados) : App.post('/api/usuarios', dados))
      .done(function () {
        modalUsuario.hide();
        App.alerta('ok', uuid
          ? 'Usuario atualizado.'
          : 'Usuario criado. Um email de verificacao foi enviado para ele definir a senha.');
        carregar();
      })
      .fail(function (xhr) {
        var p = App.tratarErro(xhr, $form);

        /*
         * 409 aqui tem duas causas: email duplicado no tenant, ou master com perfis
         * vinculados -- master e perfil sao excludentes na API. O texto do detail
         * distingue, e cada uma tem um caminho de correcao diferente.
         */
        if (p.status === 409) {
          if (/master/i.test(p.detail || '')) {
            $('#aviso-master').removeClass('d-none');
          } else {
            $('[name="email"]', $form).addClass('is-invalid');
            $('<div class="invalid-feedback"></div>').text(p.detail)
              .insertAfter($('[name="email"]', $form));
          }
        }
      })
      .always(function () { App.ocupar($botao, false); });
  });

  /* --- Perfis do usuario ------------------------------------------------------------- */

  $('#tabela-usuarios').on('click', '.btn-perfis', function () {
    var uuid = $(this).data('uuid');
    var ehMaster = !!$(this).data('master');

    $('#perfis-uuid').val(uuid);
    $('#perfis-usuario').text($(this).data('nome'));

    // Master nao recebe perfil: a API responde 409. Desabilitar aqui evita o operador
    // montar um conjunto inteiro para descobrir no envio que nao valia.
    $('#perfis-bloqueado').toggleClass('d-none', !ehMaster);
    $('#btn-salvar-perfis').prop('disabled', ehMaster);

    $('#perfis-lista').html('<div class="skeleton mb-2" style="height:2rem"></div>');

    modalPerfis.show();

    $.when(App.get('/api/perfis'), App.get('/api/usuarios/' + uuid + '/perfis'))
      .done(function (todos, doUsuario) {
        perfisDisponiveis = todos[0].dados || [];

        var vinculados = (doUsuario[0].dados || []).map(function (p) { return p.id; });

        if (!perfisDisponiveis.length) {
          $('#perfis-lista').html('<div class="estado-vazio py-4"><i class="bi bi-shield"></i>' +
            'Nenhum perfil cadastrado.</div>');
          return;
        }

        $('#perfis-lista').html(perfisDisponiveis.map(function (p) {
          var inativo = p.status !== 1;

          return '<div class="form-check py-1">' +
            '<input class="form-check-input" type="checkbox" value="' + p.id +
              '" id="perfil-' + p.id + '"' +
              (vinculados.indexOf(p.id) >= 0 ? ' checked' : '') +
              (ehMaster ? ' disabled' : '') + '>' +
            '<label class="form-check-label" for="perfil-' + p.id + '">' +
              App.escapar(p.name) +
              (inativo ? ' <span class="badge-suave neutro">inativo</span>' : '') +
              (p.isSystem ? ' <span class="badge-suave info">sistema</span>' : '') +
              '<span class="d-block small" style="color:var(--text-secondary)">' +
                App.escapar(p.description || '') + '</span>' +
            '</label></div>';
        }).join(''));
      })
      .fail(function (xhr) { App.tratarErro(xhr); });
  });

  $('#btn-salvar-perfis').on('click', function () {
    var $botao = $(this);
    var uuid = $('#perfis-uuid').val();

    var ids = $('#perfis-lista input:checked').map(function () {
      return parseInt(this.value, 10);
    }).get();

    App.ocupar($botao, true);

    App.put('/api/usuarios/' + uuid + '/perfis', { profileIds: ids })
      .done(function () {
        modalPerfis.hide();
        App.alerta('ok', 'Perfis atualizados.');
      })
      .fail(function (xhr) { App.tratarErro(xhr); })
      .always(function () { App.ocupar($botao, false); });
  });

  $(function () {
    modalUsuario = new bootstrap.Modal(document.getElementById('modal-usuario'));
    modalPerfis = new bootstrap.Modal(document.getElementById('modal-perfis'));

    // Telefone com mascara. Inputmask cuida so disto -- data e Flatpickr, selecao e Select2.
    if (window.Inputmask) {
      Inputmask({ mask: ['(99) 9999-9999', '(99) 99999-9999'], keepStatic: true })
        .mask(document.getElementById('u-phone'));
    }

    carregar();
  });
})(jQuery);
