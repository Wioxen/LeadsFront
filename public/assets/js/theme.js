/*
 * Tema claro/escuro.
 *
 * A resolucao inicial NAO acontece aqui -- ela roda num script inline no <head>, antes da
 * primeira pintura. Fazer isso no DOMContentLoaded produz um flash branco a cada
 * navegacao, e numa aplicacao de pagina inteira isso e a cada clique.
 *
 * Este arquivo cuida da TROCA, depois que a pagina existe.
 */
window.Tema = (function () {
  'use strict';

  var CHAVE = 'crm-tema';

  function atual() {
    return document.documentElement.getAttribute('data-bs-theme') || 'light';
  }

  function aplicar(tema) {
    document.documentElement.setAttribute('data-bs-theme', tema);

    try {
      localStorage.setItem(CHAVE, tema);
    } catch (e) {
      // Modo privativo bloqueia o storage. O tema vale para esta pagina e nao persiste --
      // preferivel a quebrar a troca inteira por causa disso.
    }

    atualizarIcone(tema);

    // Avisa quem nao le CSS. ApexCharts pinta eixo, grade e legenda por opcao de
    // JavaScript: sem recriar, o grafico fica com texto escuro sobre fundo escuro.
    document.dispatchEvent(new CustomEvent('tema:mudou', { detail: { tema: tema } }));
  }

  function alternar() {
    aplicar(atual() === 'dark' ? 'light' : 'dark');
  }

  function atualizarIcone(tema) {
    var icone = document.querySelector('#btn-tema i');

    if (icone) {
      icone.className = tema === 'dark' ? 'bi fa-sun' : 'bi fa-moon';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    atualizarIcone(atual());

    var botao = document.getElementById('btn-tema');

    if (botao) {
      botao.addEventListener('click', alternar);
    }
  });

  return { atual: atual, aplicar: aplicar, alternar: alternar };
})();
