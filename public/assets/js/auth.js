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

      // Conferido aqui porque a API nao tem campo de confirmacao -- mandar as duas para
      // ela nao acusaria a divergencia, so gravaria a primeira.
      if (valor !== confirmacao) {
        $('#confirmacao').addClass('is-invalid');
        $('<div class="invalid-feedback">As senhas nao conferem.</div>').insertAfter('#confirmacao');
        $('#confirmacao').trigger('focus');
        return;
      }

      App.ocupar($botao, true);

      App.post($senha.data('acao'), {
        token: $('[name="token"]', $senha).val(),
        senha: valor
      })
        .done(function (r) {
          /*
           * O POST devolve TokenResponse e o PHP ja gravou a sessao. Vai direto ao
           * dashboard: mandar o usuario fazer login logo depois de definir a propria senha
           * seria um passo sem funcao.
           */
          App.alerta('ok', 'Senha definida. Bem-vindo!');
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
})(jQuery);
