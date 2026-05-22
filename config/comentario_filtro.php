<?php

function normalizarTextoComentario(string $texto): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n'
    ]);

    return preg_replace('/\s+/u', ' ', trim($texto)) ?? '';
}

function comentarioContieneGroseria(string $texto): bool
{
    $textoNormalizado = normalizarTextoComentario($texto);

    $palabrasProhibidas = [
        'marica',
        'gonorrea',
        'hijueputa',
        'hijo de puta',
        'puta',
        'puto',
        'malparido',
        'careverga',
        'verga',
        'mierda',
        'pendejo',
        'pendeja',
        'imbecil',
        'idiota',
        'estupido',
        'estupida',
        'cabron',
        'cabrona',
        'culero',
        'culera'
    ];

    foreach ($palabrasProhibidas as $palabra) {
        $patron = '/(^|[^\p{L}])' . preg_quote($palabra, '/') . '($|[^\p{L}])/u';

        if (preg_match($patron, $textoNormalizado)) {
            return true;
        }
    }

    return false;
}
