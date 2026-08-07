<?php

declare(strict_types=1);

/*
 * Calcula o mesmo digest que /version devolve, a partir dos arquivos LOCAIS.
 *
 * Serve para responder "o que esta no ar e o que eu tenho aqui?" com uma comparacao, em vez
 * de pedir para alguem testar a tela. Uso:
 *
 *   php bin/versao.php
 *   curl -s https://leads.digite.com.br/version
 */

require __DIR__ . '/../src/Versao.php';

$v = \App\Versao::calcular(dirname(__DIR__));

printf("digest   %s\narquivos %d\nbytes    %d\n", $v['digest'], $v['arquivos'], $v['bytes']);
