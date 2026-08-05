/* Leads: listagem, cadastro, edicao e exclusao. */
(function ($) {
  'use strict';

  if ($('body').data('page') !== 'leads') {
    return;
  }

  var tabela = null;
  var modal = null;

  function montarTabela(dados) {
    $('#area-tabela').addClass('d-none');
    $('#tabela-leads').removeClass('d-none');

    // Sem destroy, cada recarga acumula uma instancia com seus observadores -- o
    // vazamento aparece como lentidao progressiva depois de meia hora de uso.
    if (tabela) {
      tabela.destroy();
      $('#tabela-leads tbody').empty();
    }

    var linhas = dados.map(function (l) {
      return [
        App.escapar(l.name),
        App.escapar(l.email),
        '<span title="' + App.escapar(App.dataLocal(l.createdAtUtc)) + '">' +
          App.relativo(l.createdAtUtc) + '</span>',
        '<div class="text-end text-nowrap">' +
          '<button class="btn btn-sm btn-outline-secondary btn-editar" ' +
            'data-uuid="' + App.escapar(l.uuid) + '" ' +
            'data-name="' + App.escapar(l.name) + '" ' +
            'data-email="' + App.escapar(l.email) + '" title="Editar">' +
            '<i class="bi bi-pencil"></i></button> ' +
          '<button class="btn btn-sm btn-outline-danger btn-excluir" ' +
            'data-uuid="' + App.escapar(l.uuid) + '" ' +
            'data-name="' + App.escapar(l.name) + '" title="Excluir">' +
            '<i class="bi bi-trash"></i></button>' +
        '</div>'
      ];
    });

    tabela = $('#tabela-leads').DataTable({
      data: linhas,
      responsive: true,
      pageLength: 25,

      /*
       * A API ja devolve do mais recente para o mais antigo -- qualquer ordenacao padrao
       * do DataTables desfaria a ordem que ela escolheu.
       */
      order: [],

      /*
       * Paginacao de EXIBICAO, sobre a lista inteira que ja veio. Nao e serverSide, e nao
       * deve virar: apontar serverSide para um endpoint que ignora start/length produziria
       * uma tela identica e uma mentira que so aparece no dia em que alguem confiar nela.
       */
      language: {
        emptyTable: 'Nenhum lead cadastrado ainda.',
        zeroRecords: 'Nenhum lead encontrado para essa busca.',
        info: 'Mostrando _START_ a _END_ de _TOTAL_',
        infoEmpty: 'Nenhum registro',
        infoFiltered: '(de _MAX_ no total)',
        lengthMenu: '_MENU_ por pagina',
        search: 'Buscar:',
        paginate: { first: 'Primeira', last: 'Ultima', next: 'Proxima', previous: 'Anterior' }
      }
    });
  }

  function carregar() {
    App.get('/api/leads')
      .done(function (r) { montarTabela(r.dados || []); })
      .fail(function (xhr) {
        if (xhr.status === 401) { return; }

        var p = App.problema(xhr);

        // O 403 aqui e esperado e precisa de mensagem clara: leads e recurso de negocio, e
        // o acesso de um Usuario comum e decidido pelos perfis, action a action. Nao ha
        // endpoint que devolva as permissoes do proprio usuario, entao o front nao tinha
        // como esconder o botao antes.
        $('#area-tabela').html(
          '<div class="estado-vazio"><i class="bi bi-shield-exclamation"></i>' +
          (p.status === 403
            ? 'Voce nao tem permissao para listar leads. Peca a um administrador que inclua a permissao no seu perfil.'
            : App.escapar(p.detail || p.title)) +
          '</div>'
        );
      });
  }

  function abrirModal(dados) {
    var $form = $('#form-lead');

    App.limparErros($form);
    $form[0].reset();

    $('[name="uuid"]', $form).val(dados ? dados.uuid : '');
    $('[name="name"]', $form).val(dados ? dados.name : '');
    $('[name="email"]', $form).val(dados ? dados.email : '');

    $('#modal-titulo').text(dados ? 'Editar lead' : 'Novo lead');

    modal.show();

    setTimeout(function () { $('#lead-name').trigger('focus'); }, 300);
  }

  /* --- Eventos ----------------------------------------------------------------------- */

  // Delegacao a partir de um container estavel: handlers ligados direto nas linhas morrem
  // quando a tabela e recarregada por AJAX.
  $('#tabela-leads').on('click', '.btn-editar', function () {
    abrirModal($(this).data());
  });

  $('#tabela-leads').on('click', '.btn-excluir', function () {
    var uuid = $(this).data('uuid');
    var nome = $(this).data('name');

    App.confirmarExclusao('o lead ' + nome).then(function (confirmado) {
      if (!confirmado) { return; }

      App.del('/api/leads/' + uuid)
        .done(function () {
          App.alerta('ok', 'Lead excluido.');
          carregar();
        })
        .fail(function (xhr) { App.tratarErro(xhr); });
    });
  });

  $('#btn-novo').on('click', function () { abrirModal(null); });

  $('#form-lead').on('submit', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $botao = $('#btn-salvar');

    App.limparErros($form);

    var dados = App.dadosDoFormulario($form);
    var uuid = dados.uuid;

    delete dados.uuid;

    App.ocupar($botao, true);

    var envio = uuid
      ? App.put('/api/leads/' + uuid, dados)
      : App.post('/api/leads', dados);

    envio
      .done(function () {
        modal.hide();
        App.alerta('ok', uuid ? 'Lead atualizado.' : 'Lead cadastrado.');
        carregar();
      })
      .fail(function (xhr) {
        var p = App.tratarErro(xhr, $form);

        // Email duplicado e 409, nao 400 com errors -- e o problema E do campo email.
        // Tratar como alerta global deixaria o usuario procurando o que corrigir.
        if (p.status === 409) {
          $('[name="email"]', $form).addClass('is-invalid');
          $('[name="email"]', $form).siblings('.invalid-feedback').remove();
          $('<div class="invalid-feedback"></div>').text(p.detail).insertAfter($('[name="email"]', $form));
        }
      })
      .always(function () { App.ocupar($botao, false); });
  });

  $(function () {
    modal = new bootstrap.Modal(document.getElementById('modal-lead'));

    carregar();

    // Vindo do dashboard por "Novo lead".
    if (new URLSearchParams(location.search).get('novo') === '1') {
      abrirModal(null);
    }
  });
})(jQuery);
