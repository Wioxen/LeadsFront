/*
 * Base do front: cliente AJAX, tratamento de erro, helpers de formulario.
 *
 * Toda chamada daqui e RELATIVA (/api/leads). A URL de api-leads.digite.com.br vive no
 * .env do PHP e nunca chega ao navegador -- nem em variavel, nem em data-*.
 */
window.App = (function ($) {
  'use strict';

  var csrf = $('meta[name="csrf-token"]').attr('content') || '';

  /* --- HTTP ------------------------------------------------------------------------- */

  /**
   * Envelope unico de requisicao. Devolve uma Promise do jQuery.
   *
   * O erro chega ao .fail() ja normalizado como ProblemDetails -- o MESMO formato da API,
   * porque o BFF repassa intacto. Ha um contrato de erro so no front inteiro.
   */
  function requisitar(metodo, url, dados) {
    return $.ajax({
      url: url,
      method: metodo,
      contentType: 'application/json; charset=utf-8',
      dataType: 'json',
      headers: { 'X-CSRF-Token': csrf },
      data: dados ? JSON.stringify(dados) : undefined
    }).fail(function (xhr) {
      // 401 nao e erro de tela: a sessao morreu e o unico caminho e o login. Tratado
      // aqui, uma vez, para nenhuma tela precisar lembrar.
      if (xhr.status === 401) {
        window.location.href = '/login?motivo=expirado';
      }
    });
  }

  function problema(xhr) {
    var corpo = xhr.responseJSON;

    if (corpo && corpo.title) {
      return corpo;
    }

    return {
      status: xhr.status || 0,
      title: 'Falha na comunicacao',
      detail: xhr.status === 0
        ? 'Sem conexao com o servidor.'
        : 'Nao foi possivel completar a operacao.'
    };
  }

  /**
   * Mapeamento de erro -> interface, conforme a secao 6 do front.md.
   *
   * 400 com 'errors' marca campo a campo; sem 'errors', vira alerta. 409 e sempre
   * acionavel (email duplicado, perfil de sistema, master com perfil). 5xx mostra o
   * traceId, que e o que o suporte usa para correlacionar.
   */
  function tratarErro(xhr, $formulario) {
    var p = problema(xhr);

    if (p.status === 400 && p.errors && $formulario && $formulario.length) {
      aplicarErrosDeCampo($formulario, p.errors);
      return p;
    }

    if (p.status >= 500 && p.traceId) {
      alerta('erro', p.detail + ' (traceId: ' + p.traceId + ')');
      return p;
    }

    alerta('erro', p.detail || p.title);

    return p;
  }

  /**
   * As chaves de 'errors' sao os nomes das propriedades do comando em PascalCase (Email,
   * Name, Master) -- NAO camelCase. O input pode ter qualquer um dos dois, entao a busca
   * tenta as duas grafias.
   */
  function aplicarErrosDeCampo($formulario, errors) {
    var $primeiro = null;

    Object.keys(errors).forEach(function (campo) {
      var camel = campo.charAt(0).toLowerCase() + campo.slice(1);
      var $input = $formulario.find('[name="' + campo + '"], [name="' + camel + '"]').first();

      if (!$input.length) {
        // Erro sem campo correspondente na tela nao pode sumir em silencio.
        alerta('erro', errors[campo].join(' '));
        return;
      }

      $input.addClass('is-invalid');

      var $feedback = $input.siblings('.invalid-feedback');

      if (!$feedback.length) {
        $feedback = $('<div class="invalid-feedback"></div>').insertAfter($input);
      }

      $feedback.text(errors[campo].join(' '));

      if (!$primeiro) {
        $primeiro = $input;
      }
    });

    // Foco no primeiro invalido. Sem isso, num formulario longo o usuario nao encontra
    // o que precisa corrigir.
    if ($primeiro) {
      $primeiro.trigger('focus');
    }
  }

  function limparErros($formulario) {
    $formulario.find('.is-invalid').removeClass('is-invalid');
    $formulario.find('.invalid-feedback').remove();
  }

  /* --- Retorno ao usuario ------------------------------------------------------------ */

  /*
   * Toastify para o que PASSA; alerta do Bootstrap para o que precisa ficar na tela ate
   * ser lido. Toast nao serve para mensagem que o usuario precisa reler.
   */
  function alerta(tipo, mensagem) {
    var cores = {
      ok: 'var(--brand-success)',
      erro: 'var(--brand-danger)',
      aviso: 'var(--brand-warning)',
      info: 'var(--brand-primary)'
    };

    Toastify({
      text: mensagem,
      duration: tipo === 'erro' ? 6000 : 3500,
      gravity: 'top',
      position: 'right',
      close: true,
      style: {
        background: cores[tipo] || cores.info,
        borderRadius: 'var(--radius-sm)',
        fontSize: '.875rem'
      }
    }).showToast();
  }

  /**
   * Confirmacao destrutiva. NOMEIA o registro -- "Tem certeza?" generico e clicado no
   * automatico. A API faz remocao fisica: nao ha desfazer.
   */
  function confirmarExclusao(nome) {
    return Swal.fire({
      title: 'Excluir ' + nome + '?',
      text: 'A exclusao e permanente e nao pode ser desfeita.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Excluir',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: getComputedStyle(document.documentElement)
        .getPropertyValue('--brand-danger').trim(),
      reverseButtons: true
    }).then(function (r) {
      return r.isConfirmed;
    });
  }

  /* --- Formulario -------------------------------------------------------------------- */

  /**
   * Desabilita o botao e mostra spinner ate a resposta. Sem isso, clique duplo vira
   * cadastro duplicado -- e em leads isso da 409, o que confunde mais do que informa.
   */
  function ocupar($botao, ocupado) {
    if (ocupado) {
      $botao.data('texto-original', $botao.html())
            .prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Aguarde...');
    } else {
      $botao.prop('disabled', false).html($botao.data('texto-original'));
    }
  }

  function dadosDoFormulario($formulario) {
    var dados = {};

    $formulario.find('[name]').each(function () {
      var $c = $(this);
      var nome = $c.attr('name');

      if (!nome || nome === '_csrf') {
        return;
      }

      if ($c.attr('type') === 'checkbox') {
        dados[nome] = $c.is(':checked');
      } else if ($c.data('numero') !== undefined) {
        dados[nome] = parseInt($c.val(), 10) || 0;
      } else {
        dados[nome] = $c.val();
      }
    });

    return dados;
  }

  /* --- Tabelas ----------------------------------------------------------------------- */

  /**
   * Inicializa um DataTable com os padroes do projeto.
   *
   * Existe para que a proxima tabela nao nasca sem o responsivePriority. Sem ele, o plugin
   * esconde as colunas da direita para a esquerda, e a de ACOES -- que e sempre a ultima --
   * e a primeira a sair. No celular o usuario ficava vendo a listagem sem conseguir editar
   * nem excluir nada.
   *
   * A prioridade e INVERTIDA: numero MENOR e mantido por mais tempo. A coluna 0 (o que
   * identifica a linha) e a ultima (as acoes) sobrevivem; email, data e categoria colapsam
   * para dentro do expansor, que e onde informacao de apoio deve ficar num telefone.
   */
  function tabela($elemento, opcoes) {
    opcoes = opcoes || {};

    var padrao = {
      responsive: true,
      pageLength: 25,

      // A API ja devolve na ordem certa; qualquer ordenacao padrao do DataTables a desfaz.
      order: [],

      columnDefs: [
        { responsivePriority: 1, targets: 0 },
        { responsivePriority: 2, targets: -1 }
      ],

      language: {
        emptyTable: 'Nenhum registro encontrado.',
        zeroRecords: 'Nenhum registro para essa busca.',
        info: 'Mostrando _START_ a _END_ de _TOTAL_',
        infoEmpty: 'Nenhum registro',
        infoFiltered: '(de _MAX_ no total)',
        lengthMenu: '_MENU_ por pagina',
        search: 'Buscar:',
        paginate: { first: 'Primeira', last: 'Ultima', next: 'Proxima', previous: 'Anterior' }
      }
    };

    var config = $.extend({}, padrao, opcoes);

    // Extend RASO de proposito: um columnDefs informado substitui o padrao inteiro, em vez
    // de se fundir por indice -- que e como $.extend(true, ...) trata arrays, produzindo
    // combinacoes que ninguem escreveu.
    config.language = $.extend({}, padrao.language, opcoes.language || {});

    return $elemento.DataTable(config);
  }

  /* --- Formatacao -------------------------------------------------------------------- */

  function escapar(texto) {
    return $('<div>').text(texto == null ? '' : texto).html();
  }

  /** Datas chegam em UTC (...Utc). Converte para o fuso do navegador. */
  function dataLocal(utc) {
    if (!utc) {
      return '--';
    }

    var d = new Date(utc.endsWith('Z') || utc.includes('+') ? utc : utc + 'Z');

    return isNaN(d) ? '--' : d.toLocaleString('pt-BR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  function relativo(utc) {
    if (!utc) {
      return '--';
    }

    var d = new Date(utc.endsWith('Z') || utc.includes('+') ? utc : utc + 'Z');
    var seg = Math.floor((Date.now() - d.getTime()) / 1000);

    if (isNaN(seg)) { return '--'; }
    if (seg < 60) { return 'agora'; }
    if (seg < 3600) { return 'ha ' + Math.floor(seg / 60) + ' min'; }
    if (seg < 86400) { return 'ha ' + Math.floor(seg / 3600) + ' h'; }
    if (seg < 2592000) { return 'ha ' + Math.floor(seg / 86400) + ' d'; }

    return dataLocal(utc).slice(0, 10);
  }

  function numero(valor) {
    return (valor || 0).toLocaleString('pt-BR');
  }

  /** Le um token do CSS. Serve ao ApexCharts, que nao enxerga variavel CSS. */
  function token(nome) {
    return getComputedStyle(document.documentElement).getPropertyValue(nome).trim();
  }

  /* --- Troca de senha ---------------------------------------------------------------- */

  /*
   * Vive aqui, e nao num arquivo de tela, porque o modal esta no LAYOUT: ele existe em toda
   * pagina autenticada, e um handler por tela seria o mesmo codigo repetido em cada uma.
   */
  $(function () {
    /*
     * #form-trocar-senha, nao #form-senha: este arquivo tambem carrega no layout anonimo, e
     * la existe um #form-senha que e a tela de DEFINIR senha. Com o id repetido, um submit
     * acionava os dois tratadores.
     */
    var $form = $('#form-trocar-senha');

    if (!$form.length) { return; }

    var modal = null;

    $form.on('submit', function (e) {
      e.preventDefault();

      var $botao = $('#btn-salvar-senha');

      App.limparErros($form);
      App.ocupar($botao, true);

      /*
       * Nenhuma validacao de forca nem de conferencia aqui.
       *
       * A regra vive na API, e duplica-la neste ponto criaria duas verdades que divergem na
       * primeira vez que uma mudar -- com a agravante de a versao do navegador ser a que o
       * usuario ve. O 400 volta com o dicionario de erros por campo, e App.tratarErro ja
       * sabe marcar cada um.
       */
      App.post('/api/trocar-senha', App.dadosDoFormulario($form))
        .done(function (r) {
          modal = modal || bootstrap.Modal.getInstance(document.getElementById('modal-senha'));

          if (modal) { modal.hide(); }

          $form[0].reset();
          App.alerta('ok', r.mensagem || 'Senha alterada.');
        })
        .fail(function (xhr) { App.tratarErro(xhr, $form); })
        .always(function () { App.ocupar($botao, false); });
    });

    // Reabrir o modal nao deve mostrar o que ficou da tentativa anterior -- nem os campos
    // preenchidos, nem as marcas de erro.
    $('#modal-senha').on('hidden.bs.modal', function () {
      App.limparErros($form);
      $form[0].reset();
    });
  });

  /* --- Shell ------------------------------------------------------------------------- */

  $(function () {
    var $corpo = $('body');

    if (localStorage.getItem('crm-sidebar') === 'recolhida') {
      $corpo.addClass('sidebar-recolhida');
    }

    // O fundo e criado uma vez e reaproveitado. Vive fora da sidebar de proposito: dentro
    // dela, herdaria o transform e o clique cairia na area errada.
    var $fundo = $('<div class="sidebar-fundo"></div>').appendTo('body');

    function alternarNoCelular(abrir) {
      $('.sidebar').toggleClass('aberta', abrir);
      $fundo.toggleClass('visivel', abrir);
    }

    $('#btn-sidebar').on('click', function () {
      if (window.matchMedia('(max-width: 991.98px)').matches) {
        alternarNoCelular(!$('.sidebar').hasClass('aberta'));
        return;
      }

      $corpo.toggleClass('sidebar-recolhida');
      localStorage.setItem('crm-sidebar',
        $corpo.hasClass('sidebar-recolhida') ? 'recolhida' : 'aberta');
    });

    // Tocar fora fecha -- e o primeiro gesto que todo mundo tenta.
    $fundo.on('click', function () { alternarNoCelular(false); });

    // Esc fecha, para quem estiver num tablet com teclado.
    $(document).on('keydown', function (e) {
      if (e.key === 'Escape') { alternarNoCelular(false); }
    });

    /*
     * Ao passar para o desktop, desfaz o estado de celular. Sem isto, girar o aparelho ou
     * redimensionar a janela com o menu aberto deixa o fundo escuro preso na tela, cobrindo
     * um conteudo que ja nao esta bloqueado.
     */
    window.matchMedia('(min-width: 992px)').addEventListener('change', function (e) {
      if (e.matches) { alternarNoCelular(false); }
    });

    if (window.AOS) {
      AOS.init({
        duration: 400,
        once: true,
        // Desligado tambem aqui: o AOS escreve estilo inline, que a media query de
        // prefers-reduced-motion nao alcanca.
        disable: window.matchMedia('(prefers-reduced-motion: reduce)').matches
      });
    }
  });

  return {
    requisitar: requisitar,
    get: function (url) { return requisitar('GET', url); },
    post: function (url, dados) { return requisitar('POST', url, dados); },
    put: function (url, dados) { return requisitar('PUT', url, dados); },
    del: function (url) { return requisitar('DELETE', url); },
    tabela: tabela,
    problema: problema,
    tratarErro: tratarErro,
    limparErros: limparErros,
    alerta: alerta,
    confirmarExclusao: confirmarExclusao,
    ocupar: ocupar,
    dadosDoFormulario: dadosDoFormulario,
    escapar: escapar,
    dataLocal: dataLocal,
    relativo: relativo,
    numero: numero,
    token: token
  };
})(jQuery);
