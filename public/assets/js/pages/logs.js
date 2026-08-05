/* Registros de log -- somente leitura. */
(function ($) {
  'use strict';

  if ($('body').data('page') !== 'logs') {
    return;
  }

  var tabela = null;

  // Espelha LogSeverity da API: Trace=0 ... Critical=5.
  var NIVEIS = {
    0: { rotulo: 'Trace',    classe: 'neutro' },
    1: { rotulo: 'Debug',    classe: 'neutro' },
    2: { rotulo: 'Info',     classe: 'info' },
    3: { rotulo: 'Warning',  classe: 'aviso' },
    4: { rotulo: 'Error',    classe: 'erro' },
    5: { rotulo: 'Critical', classe: 'erro' }
  };

  function montarTabela(dados) {
    $('#area-tabela').addClass('d-none');
    $('#tabela-logs').removeClass('d-none');

    if (tabela) {
      tabela.destroy();
      $('#tabela-logs tbody').empty();
    }

    tabela = $('#tabela-logs').DataTable({
      data: dados.map(function (e) {
        var nivel = NIVEIS[e.severity] || { rotulo: String(e.severity), classe: 'neutro' };

        var mensagem = '<div style="font-family:ui-monospace,monospace;font-size:.8125rem">' +
          App.escapar(e.message) + '</div>';

        // Excecao num <details> recolhido: ela e longa e quebraria a leitura da tabela,
        // mas some-la esconderia justamente o que interessa quando algo falha.
        if (e.exception) {
          mensagem += '<details class="mt-1"><summary class="small" style="cursor:pointer;color:var(--text-secondary)">' +
            'Excecao</summary><pre class="small mt-1 mb-0 p-2" style="background:var(--surface-muted);' +
            'border-radius:var(--radius-sm);overflow-x:auto;white-space:pre-wrap">' +
            App.escapar(e.exception) + '</pre></details>';
        }

        return [
          '<span class="badge-suave ' + nivel.classe + '">' + nivel.rotulo + '</span>',
          '<code class="small">' + App.escapar(e.category || '') + '</code>',
          mensagem,
          '<span class="text-nowrap small" title="' + App.escapar(App.dataLocal(e.createdAtUtc)) + '">' +
            App.relativo(e.createdAtUtc) + '</span>'
        ];
      }),
      responsive: true,
      pageLength: 25,
      order: [],           // a API ja devolve os mais recentes primeiro
      language: {
        emptyTable: 'Nenhum evento registrado nesta organizacao.',
        zeroRecords: 'Nenhum evento encontrado.',
        info: 'Mostrando _START_ a _END_ de _TOTAL_',
        infoEmpty: 'Nenhum registro',
        lengthMenu: '_MENU_ por pagina',
        search: 'Buscar:',
        paginate: { first: 'Primeira', last: 'Ultima', next: 'Proxima', previous: 'Anterior' }
      }
    });
  }

  $(function () {
    App.get('/api/logs')
      .done(function (r) { montarTabela(r.dados || []); })
      .fail(function (xhr) {
        if (xhr.status === 401) { return; }
        var p = App.problema(xhr);
        $('#area-tabela').html('<div class="estado-vazio"><i class="bi bi-exclamation-triangle"></i>' +
          App.escapar(p.detail || p.title) + '</div>');
      });
  });
})(jQuery);
