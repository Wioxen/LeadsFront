<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Versao;

/**
 * Diz qual versao do front esta no ar.
 *
 * Sem autenticacao e sem cache: o valor so serve se refletir o instante da pergunta.
 */
final class VersionController extends Controller
{
    public function mostrar(): never
    {
        $v = Versao::calcular();

        $this->json([
            'digest'   => $v['digest'],
            'arquivos' => $v['arquivos'],
            'bytes'    => $v['bytes'],
            'agoraUtc' => gmdate('c'),
        ]);
    }
}
