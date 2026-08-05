<#
.SYNOPSIS
    Baixa as bibliotecas de terceiro para public/assets/vendor/ e a fonte Inter para
    public/assets/fonts/.

.DESCRIPTION
    As bibliotecas sao servidas LOCALMENTE, nunca por CDN. O ponto inteiro da arquitetura
    BFF e que o navegador so fale com a nossa origem: uma tag de CDN devolve ao terceiro a
    capacidade de ver cada visita ao CRM, de ficar fora do ar por nos e -- no caso de
    script -- de executar codigo na pagina onde a sessao vive.

    O conteudo baixado fica FORA do versionamento (ver .gitignore). Rode este script apos
    clonar o repositorio.

.EXAMPLE
    .\tools\vendor.ps1
#>
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$repo = Split-Path -Parent $PSScriptRoot
$vendor = Join-Path $repo 'public\assets\vendor'
$fontes = Join-Path $repo 'public\assets\fonts'

# Versoes FIXAS. 'latest' num CDN e uma atualizacao silenciosa entrando em producao sem
# ninguem revisar -- exatamente o que este projeto evita ao servir localmente.
$arquivos = @(
    @{ destino = 'jquery\jquery.min.js';                          url = 'https://code.jquery.com/jquery-3.7.1.min.js' }

    @{ destino = 'bootstrap\bootstrap.min.css';                   url = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' }
    @{ destino = 'bootstrap\bootstrap.bundle.min.js';             url = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js' }

    @{ destino = 'bootstrap-icons\bootstrap-icons.css';           url = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' }
    @{ destino = 'bootstrap-icons\fonts\bootstrap-icons.woff2';   url = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2' }
    @{ destino = 'bootstrap-icons\fonts\bootstrap-icons.woff';    url = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff' }

    @{ destino = 'datatables\jquery.dataTables.min.js';           url = 'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js' }
    @{ destino = 'datatables\dataTables.bootstrap5.min.js';       url = 'https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js' }
    @{ destino = 'datatables\dataTables.bootstrap5.min.css';      url = 'https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css' }
    @{ destino = 'datatables\dataTables.responsive.min.js';       url = 'https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js' }

    @{ destino = 'select2\select2.min.js';                        url = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js' }
    @{ destino = 'select2\select2-bootstrap-5.min.css';           url = 'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css' }

    @{ destino = 'flatpickr\flatpickr.min.js';                    url = 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js' }
    @{ destino = 'flatpickr\flatpickr.min.css';                   url = 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css' }
    @{ destino = 'flatpickr\pt.js';                               url = 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/pt.js' }

    @{ destino = 'inputmask\inputmask.min.js';                    url = 'https://cdn.jsdelivr.net/npm/inputmask@5.0.9/dist/jquery.inputmask.min.js' }

    @{ destino = 'apexcharts\apexcharts.min.js';                  url = 'https://cdn.jsdelivr.net/npm/apexcharts@3.45.2/dist/apexcharts.min.js' }

    @{ destino = 'sweetalert2\sweetalert2.all.min.js';            url = 'https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js' }
    @{ destino = 'sweetalert2\sweetalert2.min.css';               url = 'https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css' }

    @{ destino = 'toastify\toastify.js';                          url = 'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.js' }
    @{ destino = 'toastify\toastify.min.css';                     url = 'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.css' }

    @{ destino = 'animate\animate.min.css';                       url = 'https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css' }

    @{ destino = 'aos\aos.js';                                    url = 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js' }
    @{ destino = 'aos\aos.css';                                   url = 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css' }
)

# Inter, um arquivo por peso. Sem fonts.googleapis.com: um <link> para la transmite o IP de
# cada usuario a cada carregamento, o que em cliente sujeito a LGPD e uma decisao a ser
# tomada, e nao um efeito colateral de copiar um trecho de codigo.
$pesos = @(
    @{ arquivo = 'Inter-Regular.woff2';  url = 'https://cdn.jsdelivr.net/fontsource/fonts/inter@latest/latin-400-normal.woff2' }
    @{ arquivo = 'Inter-Medium.woff2';   url = 'https://cdn.jsdelivr.net/fontsource/fonts/inter@latest/latin-500-normal.woff2' }
    @{ arquivo = 'Inter-SemiBold.woff2'; url = 'https://cdn.jsdelivr.net/fontsource/fonts/inter@latest/latin-600-normal.woff2' }
    @{ arquivo = 'Inter-Bold.woff2';     url = 'https://cdn.jsdelivr.net/fontsource/fonts/inter@latest/latin-700-normal.woff2' }
)

function Get-Arquivo([string]$url, [string]$destino) {
    $pasta = Split-Path -Parent $destino

    if (-not (Test-Path $pasta)) {
        New-Item -ItemType Directory -Force $pasta | Out-Null
    }

    try {
        Invoke-WebRequest -Uri $url -OutFile $destino -UseBasicParsing -TimeoutSec 60
        Write-Host "  ok    $(Split-Path -Leaf $destino)" -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "  FALHA $(Split-Path -Leaf $destino)  <- $url" -ForegroundColor Red
        Write-Host "        $($_.Exception.Message)" -ForegroundColor DarkGray
        return $false
    }
}

Write-Host "[vendor] bibliotecas -> public/assets/vendor/" -ForegroundColor Cyan

$falhas = 0

foreach ($item in $arquivos) {
    if (-not (Get-Arquivo $item.url (Join-Path $vendor $item.destino))) { $falhas++ }
}

Write-Host "`n[vendor] fonte Inter -> public/assets/fonts/" -ForegroundColor Cyan

foreach ($peso in $pesos) {
    if (-not (Get-Arquivo $peso.url (Join-Path $fontes $peso.arquivo))) { $falhas++ }
}

Write-Host ''

if ($falhas -gt 0) {
    Write-Host "[vendor] $falhas arquivo(s) falharam. A tela vai carregar SEM eles -- confira antes de testar." -ForegroundColor Yellow
    exit 1
}

Write-Host '[vendor] tudo baixado.' -ForegroundColor Green
