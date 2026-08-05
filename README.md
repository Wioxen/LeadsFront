# LeadsFront

CRM multi-tenant em PHP + Bootstrap 5.3 + jQuery, consumindo a API do projeto `LeadsApi`.

O PHP atua como **BFF**: o navegador nunca fala com a API diretamente. O `accessToken`
vive na sessão do PHP, e todo XHR do jQuery vai para a própria origem.

## A especificação NÃO mora aqui

A fonte de verdade deste front é o documento do outro repositório:

```
D:\Projetos\LeadsApi\front.md
```

Ele descreve arquitetura, telas, design system, tratamento de erro e as armadilhas de cada
fluxo. O histórico versionado dele está em `D:\Projetos\LeadsApi\docs\front\`, gerado por
`tools\doc.ps1`.

A ordem de trabalho é **documento primeiro, código depois**:

```
1. edita o front.md
2. tools\doc.ps1 front save "descricao"
3. tools\doc.ps1 front diff <versao-anterior>    -> o delta
4. implementa aqui: contexto = front.md inteiro, tarefa = so o delta
5. revisa por git diff, neste repositorio
```

Nunca regenere o projeto inteiro a partir do documento depois da primeira vez. Regeneração
não é determinística: descarta ajuste feito à mão e produz um diff grande demais para
alguém revisar de verdade — que é exatamente quando as regressões passam.

O contrato da API, onde divergir do `front.md`, é decidido pelo OpenAPI:

```
https://api-leads.digite.com.br/openapi/v1.json
https://api-leads.digite.com.br/scalar/
```

## Instalação

```
copy .env.example .env
```

Preencha o `.env` e sirva **apenas a pasta `public/`**. Se `src/` ficar acessível pela web,
o `.env` e o cliente HTTP viram leitura pública.

As bibliotecas de terceiros vão em `public/assets/vendor/` e a fonte Inter em
`public/assets/fonts/` — os dois fora do versionamento, baixados na instalação. Nada de
CDN: o navegador só deve falar com a sua origem.

## Estrutura

```
public/          única pasta exposta pelo servidor web
  index.php      front controller
  assets/
    css/         tokens.css (cores, raio, sombra) + app.css
    js/          app.js, theme.js, pages/*.js
    fonts/       Inter (woff2)          -- fora do git
    vendor/      bibliotecas            -- fora do git

src/             nunca exposto pela web
  Config.php     leitura do .env
  Http/          ApiClient (cURL + Bearer), ApiException (ProblemDetails)
  Auth/          Session (token, claims), Guard (login, papel)
  Controllers/   um por recurso
  Views/         layout.php, partials/, pages/
```

## Requisitos

- PHP 8.2+
- extensão **cURL** habilitada — única dependência externa obrigatória
