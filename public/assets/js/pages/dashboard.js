/* Dashboard: KPIs, grafico mensal, ultimos leads e atividades. */
(function ($) {
  'use strict';

  if ($('body').data('page') !== 'dashboard') {
    return;
  }

  var periodo = new URLSearchParams(location.search).get('periodo') || '30';
  var grafico = null;
  var ultimaSerie = null;

  /* --- Grafico ---------------------------------------------------------------------- */

  /*
   * ApexCharts NAO enxerga variavel CSS: cor de eixo, grade e legenda e opcao de
   * JavaScript. Por isso as cores sao lidas dos tokens a cada montagem -- e por isso o
   * grafico e refeito quando o tema muda.
   */
  function opcoes(serie) {
    return {
      chart: {
        type: 'area',
        height: 280,
        fontFamily: 'Inter, sans-serif',
        toolbar: { show: false },       // o menu de exportacao nao combina e ninguem usa
        animations: { speed: 250 },
        background: 'transparent'
      },
      theme: { mode: document.documentElement.getAttribute('data-bs-theme') || 'light' },
      series: [{ name: 'Leads', data: serie.valores }],
      colors: [App.token('--brand-primary')],
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 2 },
      fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .02, stops: [0, 100] }
      },
      grid: { borderColor: App.token('--border'), strokeDashArray: 4 },
      xaxis: {
        categories: serie.rotulos.map(function (ym) {
          var p = ym.split('-');
          return ['jan', 'fev', 'mar', 'abr', 'mai', 'jun',
                  'jul', 'ago', 'set', 'out', 'nov', 'dez'][parseInt(p[1], 10) - 1] + '/' + p[0].slice(2);
        }),
        labels: { style: { colors: App.token('--text-secondary'), fontSize: '11px' } },
        axisBorder: { color: App.token('--border') },
        axisTicks: { color: App.token('--border') }
      },
      yaxis: {
        labels: {
          style: { colors: App.token('--text-secondary'), fontSize: '11px' },
          formatter: function (v) { return Math.round(v); }
        }
      },
      tooltip: { theme: document.documentElement.getAttribute('data-bs-theme') || 'light' }
    };
  }

  function desenharGrafico(serie) {
    ultimaSerie = serie;

    var vazio = serie.valores.every(function (v) { return v === 0; });

    if (vazio) {
      // Serie vazia nao e grafico vazio: eixos soltos parecem defeito.
      $('#grafico-mensal').html(
        '<div class="estado-vazio"><i class="fa-solid fa-chart-column"></i>' +
        'Nenhum lead cadastrado nos ultimos 12 meses.</div>'
      );
      return;
    }

    if (grafico) {
      grafico.destroy();          // sem destroy, cada troca de tema acumula uma instancia
      grafico = null;
    }

    $('#grafico-mensal').empty();
    grafico = new ApexCharts(document.querySelector('#grafico-mensal'), opcoes(serie));
    grafico.render();
  }

  /* --- Render ------------------------------------------------------------------------ */

  function renderKpis(d) {
    $('#kpi-total').text(App.numero(d.total));
    $('#kpi-novos').text(App.numero(d.novos));

    var $v = $('#kpi-variacao');

    // Omitido quando nao ha periodo anterior: "+100%" contra zero e matematicamente vazio
    // e parece otimo.
    if (d.variacaoNovos === null || d.variacaoNovos === undefined) {
      $v.text('').removeClass('sobe desce');
      return;
    }

    var sobe = d.variacaoNovos >= 0;

    $v.removeClass('sobe desce')
      .addClass(sobe ? 'sobe' : 'desce')
      .html((sobe ? '↑ ' : '↓ ') + Math.abs(d.variacaoNovos).toLocaleString('pt-BR') +
            '% <span style="color:var(--text-muted)">vs. periodo anterior</span>');
  }

  function renderUltimos(lista) {
    if (!lista.length) {
      $('#ultimos-leads').html(
        '<div class="estado-vazio"><i class="fa-solid fa-users"></i>' +
        'Nenhum lead cadastrado ainda.' +
        '<div class="mt-3"><a href="/leads?novo=1" class="btn btn-primary btn-sm">Cadastrar o primeiro</a></div></div>'
      );
      return;
    }

    var html = lista.map(function (l) {
      return '<div class="d-flex align-items-center gap-3 py-2 border-bottom">' +
        '<div class="rounded-circle" style="width:32px;height:32px;background:var(--surface-muted);' +
        'display:grid;place-items:center;font-weight:600;font-size:.75rem;color:var(--text-secondary)">' +
        App.escapar((l.name || '?').charAt(0).toUpperCase()) + '</div>' +
        '<div class="flex-grow-1 min-width-0">' +
        '<div class="fw-medium text-truncate">' + App.escapar(l.name) + '</div>' +
        '<div class="small text-truncate" style="color:var(--text-secondary)">' + App.escapar(l.email) + '</div>' +
        '</div>' +
        '<div class="small text-nowrap" style="color:var(--text-muted)">' + App.relativo(l.createdAtUtc) + '</div>' +
        '</div>';
    }).join('');

    $('#ultimos-leads').html(html);
  }

  function renderAtividades(lista) {
    var $alvo = $('#atividades');

    if (!$alvo.length) {
      return;   // Usuario comum: o card nem foi renderizado (o log exige Admin ou master)
    }

    if (!lista || !lista.length) {
      $alvo.html('<div class="estado-vazio py-4"><i class="fa-solid fa-file-lines"></i>Nenhum evento registrado.</div>');
      return;
    }

    var nivel = { 0: 'neutro', 1: 'neutro', 2: 'info', 3: 'aviso', 4: 'erro', 5: 'erro' };

    $alvo.html(lista.map(function (e) {
      return '<div class="d-flex align-items-start gap-2 py-2 border-bottom">' +
        '<span class="badge-suave ' + (nivel[e.severity] || 'neutro') + '">' +
        App.escapar(e.severityName || e.severity) + '</span>' +
        '<div class="flex-grow-1 min-width-0">' +
        '<div class="small text-truncate" style="font-family:ui-monospace,monospace">' +
        App.escapar(e.message) + '</div>' +
        '<div style="font-size:.75rem;color:var(--text-muted)">' + App.relativo(e.createdAtUtc) + '</div>' +
        '</div></div>';
    }).join(''));
  }

  /* --- Carga ------------------------------------------------------------------------- */

  function carregar() {
    App.get('/api/dashboard?periodo=' + encodeURIComponent(periodo))
      .done(function (d) {
        renderKpis(d);
        desenharGrafico(d.serieMensal);
        renderUltimos(d.ultimos);
        renderAtividades(d.atividades);

        /*
         * O aviso de escala nao e decoracao. A agregacao roda no BFF sobre a listagem
         * inteira, e a fronteira conhecida e ~5.000 leads por tenant. Mostrar o numero
         * medido e o que faz alguem migrar a conta para a API antes de virar reclamacao.
         */
        if (d.excedeuLimite) {
          $('#aviso-escala').removeClass('d-none').find('span').text(
            'A agregacao deste dashboard roda no servidor do front sobre a listagem inteira (' +
            App.numero(d.total) + ' leads, ' + d.agregacaoMs + ' ms). ' +
            'Acima de 5.000 leads isso precisa virar um endpoint de agregacao na API.'
          );
        }
      })
      .fail(function (xhr) {
        if (xhr.status === 401) { return; }

        var p = App.problema(xhr);

        /*
         * 403 NAO e falha: e a resposta correta para quem ainda nao tem perfil.
         *
         * Usuario recem-criado nasce sem perfil nenhum, e o painel agrega a listagem de
         * leads -- entao a PRIMEIRA tela dele era um erro com botao de "tentar de novo",
         * que nunca ia funcionar. Tratado como estado, a tela diz o que falta e a quem
         * pedir, sem oferecer uma acao inutil.
         */
        if (xhr.status === 403) {
          $('#ultimos-leads').html(
            '<div class="estado-vazio"><i class="fa-solid fa-lock"></i>' +
            'Voce ainda nao tem acesso aos leads.' +
            '<div class="small mt-1" style="color:var(--text-secondary)">' +
            'Peca um perfil a quem administra a sua organizacao.</div></div>'
          );

          // Zera os indicadores: deixar "--" ou numeros de outra carga sugeriria que os
          // dados existem e nao carregaram, quando o caso e nao haver acesso a eles.
          renderKpis({ total: 0, novos: 0, comEmail: 0, semEmail: 0 });

          return;
        }

        $('#ultimos-leads').html(
          '<div class="estado-vazio"><i class="fa-solid fa-triangle-exclamation"></i>' +
          App.escapar(p.detail || p.title) +
          (p.traceId ? '<div class="small mt-1" style="color:var(--text-muted)">traceId: ' +
            App.escapar(p.traceId) + '</div>' : '') +
          '<div class="mt-3"><button class="btn btn-sm btn-outline-secondary" id="btn-retentar">' +
          'Tentar de novo</button></div></div>'
        );
      });
  }

  /* --- Eventos ----------------------------------------------------------------------- */

  $('#filtro-periodo').on('click', 'button', function () {
    $('#filtro-periodo button').removeClass('active');
    $(this).addClass('active');

    periodo = String($(this).data('periodo'));

    // Vai para a querystring, e nao so para a memoria do JS: assim o F5 preserva o
    // contexto e o link pode ser compartilhado.
    history.replaceState(null, '', '/?periodo=' + periodo);

    carregar();
  });

  $(document).on('click', '#btn-retentar', carregar);

  // ApexCharts nao le CSS: sem redesenhar, o grafico fica com texto escuro no fundo escuro.
  document.addEventListener('tema:mudou', function () {
    if (ultimaSerie) {
      desenharGrafico(ultimaSerie);
    }
  });

  $(function () {
    $('#filtro-periodo button').removeClass('active')
      .filter('[data-periodo="' + periodo + '"]').addClass('active');

    carregar();
  });
})(jQuery);
