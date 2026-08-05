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

      /*
       * So aparece para quem NUNCA verificou. Depois de verificada, a conta nao volta a
       * esse estado -- nem pedir redefinicao de senha a desverifica --, entao o botao ali
       * seria um controle que a API recusaria em silencio (o resend so enxerga contas
       * pendentes).
       */
      var reenviar = u.verifiedAtUtc
        ? ''
        : '<button class="btn btn-sm btn-outline-secondary btn-reenviar" data-uuid="' +
          App.escapar(u.uuid) + '" data-email="' + App.escapar(u.email) +
          '" title="Reenviar email de verificacao"><i class="bi bi-envelope-arrow-up"></i></button> ';

      /*
       * Master nao tem perfil, e nao pode ter: a API recusa o vinculo com 409, porque as
       * duas coisas sao excludentes. Como a promocao a master exige que os perfis ja
       * tenham sido removidos, um master sempre tem zero -- nao ha conjunto a exibir nem a
       * limpar.
       *
       * Antes o botao aparecia e o modal abria desabilitado, explicando. Era um caminho
       * que so servia para dizer "nao": esconder e mais honesto do que oferecer e recusar.
       */
      var perfis = u.master
        ? ''
        : '<button class="btn btn-sm btn-outline-secondary btn-perfis" data-uuid="' + App.escapar(u.uuid) +
          '" data-nome="' + nome + '" title="Perfis">' +
          '<i class="bi bi-shield-lock"></i></button> ';

      return [
        nome,
        App.escapar(u.email),
        papel,
        situacao + verificado,
        '<div class="acoes-linha">' +
          reenviar +
          perfis +
          '<button class="btn btn-sm btn-outline-secondary btn-editar" data-usuario=\'' +
            App.escapar(JSON.stringify(u)) + '\' title="Editar"><i class="bi bi-pencil"></i></button> ' +
          '<button class="btn btn-sm btn-outline-danger btn-excluir" data-uuid="' + App.escapar(u.uuid) +
            '" data-nome="' + nome + '" title="Excluir"><i class="bi bi-trash"></i></button>' +
        '</div>'
      ];
    });

    tabela = App.tabela($('#tabela-usuarios'), {
      data: linhas,
      language: {
        emptyTable: 'Nenhum usuario cadastrado.',
        zeroRecords: 'Nenhum usuario encontrado.'
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

  $('#tabela-usuarios').on('click', '.btn-reenviar', function () {
    var $botao = $(this);
    var uuid = $botao.data('uuid');
    var email = $botao.data('email');

    Swal.fire({
      title: 'Reenviar verificacao?',
      html: 'Um novo link sera enviado para <strong>' + App.escapar(email) + '</strong>.' +
            '<br><span class="small" style="color:var(--text-secondary)">' +
            'O link anterior deixa de valer.</span>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Reenviar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true
    }).then(function (r) {
      if (!r.isConfirmed) { return; }

      // App.ocupar troca o conteudo por "Aguarde...", que estica um botao de icone e
      // desalinha a coluna inteira. Aqui basta trocar o icone e desabilitar.
      var $icone = $botao.find('i');

      $botao.prop('disabled', true);
      $icone.attr('class', 'spinner-border spinner-border-sm');

      App.post('/api/usuarios/' + uuid + '/reenviar-verificacao')
        .done(function (resp) { App.alerta('ok', resp.mensagem); })
        .fail(function (xhr) { App.tratarErro(xhr); })
        .always(function () {
          $botao.prop('disabled', false);
          $icone.attr('class', 'bi bi-envelope-arrow-up');
        });
    });
  });

  $('#tabela-usuarios').on('click', '.btn-editar', function () {
    abrirModal($(this).data('usuario'));
  });

  $('#tabela-usuarios').on('click', '.btn-excluir', function () {
    var uuid = $(this).data('uuid');
    var nome = $(this).data('nome');
    var usuario = $(this).closest('tr').find('.btn-editar').data('usuario');

    App.confirmarExclusao('o usuario ' + nome).then(function (ok) {
      if (!ok) { return; }

      App.del('/api/usuarios/' + uuid)
        .done(function () { App.alerta('ok', 'Usuario excluido.'); carregar(); })
        .fail(function (xhr) {
          var p = App.problema(xhr);

          /*
           * 409 aqui e quase sempre a mesma coisa: o usuario ja produziu registros de log,
           * e loggers.user_id e RESTRICT -- apagar o autor destruiria a trilha. Na pratica
           * quem ja usou o sistema nunca mais pode ser excluido.
           *
           * Um toast com "nao pode ser excluido" deixaria o operador sem saida, quando a
           * saida existe e e outra: desativar. Oferece-la aqui evita que ele saia
           * procurando um botao que nao vai encontrar.
           */
          if (p.status === 409 && usuario) {
            Swal.fire({
              title: 'Nao e possivel excluir',
              text: p.detail,
              icon: 'info',
              showCancelButton: true,
              confirmButtonText: 'Desativar o acesso',
              cancelButtonText: 'Cancelar',
              reverseButtons: true
            }).then(function (r) {
              if (!r.isConfirmed) { return; }

              // Status 2 = Inactive. O usuario para de autenticar e o historico continua
              // atribuivel a ele.
              App.put('/api/usuarios/' + uuid, {
                firstName: usuario.firstName,
                lastName: usuario.lastName,
                email: usuario.email,
                phone: usuario.phone,
                phoneWhats: !!usuario.phoneWhats,
                level: usuario.level || 0,
                master: !!usuario.master,
                status: 2
              })
                .done(function () { App.alerta('ok', 'Acesso desativado.'); carregar(); })
                .fail(function (x2) { App.tratarErro(x2); });
            });

            return;
          }

          App.tratarErro(xhr);
        });
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

    $('#perfis-uuid').val(uuid);
    $('#perfis-usuario').text($(this).data('nome'));

    /*
     * Nao ha mais tratamento de master aqui: o botao nao existe para eles.
     *
     * Sobra uma janela estreita -- a linha da tabela pode estar velha se alguem promoveu
     * o usuario a master em outra aba. Nesse caso o PUT volta 409 e o App.tratarErro
     * mostra a mensagem da API, que ja explica o motivo. Preferivel a manter aqui um ramo
     * que nunca roda e que o proximo leitor tomaria por caminho vivo.
     */
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
              (vinculados.indexOf(p.id) >= 0 ? ' checked' : '') + '>' +
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
