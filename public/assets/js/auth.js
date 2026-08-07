/*
 * Formularios das telas anonimas.
 *
 * Um arquivo so para as quatro telas (esqueci-senha, reenviar-verificacao, definir-senha e
 * redefinir-senha) porque o comportamento e o mesmo e cada handler se guarda pela presenca
 * do proprio formulario. Um arquivo por tela aqui produziria duas copias do mesmo codigo,
 * que e como uma delas fica para tras na primeira correcao.
 *
 * Carregado pelo layout 'auth', e nao pelo data-page.
 */
(function ($) {
  'use strict';

  /* --- Envio de link: esqueci-senha e reenviar-verificacao --------------------------- */

  var $recuperar = $('#form-recuperar');

  if ($recuperar.length) {
    $recuperar.on('submit', function (e) {
      e.preventDefault();

      var $botao = $('#btn-enviar');
      var acao = $recuperar.data('acao') || '/api/esqueci-senha';

      App.limparErros($recuperar);
      App.ocupar($botao, true);

      App.post(acao, { email: $('#email').val() })
        .done(function (r) {
          /*
           * A MESMA mensagem exista o email ou nao. A API responde 202 nos dois casos, e
           * diferenciar aqui transformaria a tela num verificador de cadastro -- qualquer
           * um descobriria quem tem conta na organizacao.
           */
          $('#resultado-texto').text(r.mensagem);
          $('#resultado').removeClass('d-none');
          $recuperar.addClass('d-none');
        })
        .fail(function (xhr) { App.tratarErro(xhr, $recuperar); })
        .always(function () { App.ocupar($botao, false); });
    });
  }

  /* --- Definicao de senha: convite e redefinicao ------------------------------------- */

  var $senha = $('#form-senha');

  if ($senha.length) {
    $senha.on('submit', function (e) {
      e.preventDefault();

      var $botao = $('#btn-definir');
      var valor = $('#senha').val();
      var confirmacao = $('#confirmacao').val();

      App.limparErros($senha);

      // Conforto, nao autoridade: evita uma ida a rede para dizer o obvio. A API valida a
      // confirmacao de novo, e e a resposta dela que vale.
      if (valor !== confirmacao) {
        $('#confirmacao').addClass('is-invalid');
        $('<div class="invalid-feedback">As senhas nao conferem.</div>').insertAfter('#confirmacao');
        $('#confirmacao').trigger('focus');
        return;
      }

      App.ocupar($botao, true);

      App.post($senha.data('acao'), {
        token: $('[name="token"]', $senha).val(),
        senha: valor,

        // Campo do contrato da API (ConfirmacaoSenha), obrigatorio e conferido contra
        // Senha. Omiti-lo faz o POST voltar 400 antes de qualquer coisa acontecer.
        confirmacaoSenha: confirmacao
      })
        .done(function (r) {
          /*
           * A API responde 204 e NAO emite token: com varias organizacoes possiveis, este
           * fluxo nao tem como saber em qual a pessoa quer entrar. Quem pergunta isso e o
           * login, entao e para la que se vai.
           */
          App.alerta('ok', 'Senha definida. Entre com ela para continuar.');
          setTimeout(function () { window.location.href = r.destino || '/'; }, 600);
        })
        .fail(function (xhr) {
          var p = App.tratarErro(xhr, $senha);

          // 400 sem 'errors' aqui costuma ser o link expirando entre abrir a pagina e
          // enviar -- o caminho util e pedir outro, nao repetir a digitacao.
          if (p.status === 400 && !p.errors) {
            $senha.addClass('d-none');
            $('.cartao-auth').append(
              '<a href="/esqueci-senha" class="btn btn-primary btn-sm w-100 mt-3">Pedir um link novo</a>'
            );
          }
        })
        .always(function () { App.ocupar($botao, false); });
    });
  }

  /* --- Segundo fator: campo do codigo ------------------------------------------------ */

  var $codigo = $('#form-codigo');

  if ($codigo.length) {
    var $campo = $('#codigo');

    /*
     * Descarta tudo que nao for digito enquanto a pessoa digita.
     *
     * Colar "408 956" ou "codigo: 408956" e comum quando vem do email, e sem esta limpeza o
     * envio gastaria uma das cinco tentativas para descobrir que o formato estava errado.
     * A API tambem recusa o formato antes de contar a tentativa, entao isto e conveniencia,
     * nao a defesa -- a defesa esta la.
     */
    $campo.on('input', function () {
      var limpo = String(this.value).replace(/\D+/g, '').slice(0, 6);

      if (limpo !== this.value) { this.value = limpo; }
    });

    /*
     * NAO envia sozinho ao completar seis digitos.
     *
     * Parece uma gentileza e custa caro aqui: um digito errado no meio dispara o envio,
     * queima uma tentativa e limpa o campo antes de a pessoa terminar de conferir. Com
     * cinco tentativas por desafio, o botao explicito e o comportamento seguro.
     */

    var $contagem = $('#contagem');

    if ($contagem.length) {
      var restam = parseInt($contagem.data('restam'), 10) || 0;

      var relogio = setInterval(function () {
        restam -= 1;

        if (restam <= 0) {
          clearInterval(relogio);

          // Quem manda e a API: ela recusaria o codigo de qualquer forma. O que muda aqui e
          // so a tela parar de sugerir que ainda da tempo.
          $contagem.text('O codigo expirou. Entre novamente para receber outro.');
          $('#btn-confirmar').prop('disabled', true);
          $campo.prop('disabled', true);

          return;
        }

        var m = Math.floor(restam / 60);
        var s = restam % 60;

        $contagem.text('O codigo expira em ' + m + ':' + (s < 10 ? '0' : '') + s + '.');
      }, 1000);
    }
  }
})(jQuery);
