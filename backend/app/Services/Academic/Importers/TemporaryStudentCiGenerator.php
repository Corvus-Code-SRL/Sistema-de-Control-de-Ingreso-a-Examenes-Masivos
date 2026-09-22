<?php

namespace App\Services\Academic\Importers;

final class TemporaryStudentCiGenerator
{
    private const PREFIX = 'TMP';

    private const SUFFIX_LENGTH = 7;

    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function generate(): string
    {
        $suffix = '';

        $maxIndex = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::SUFFIX_LENGTH; $i++) {
            $suffix .= self::ALPHABET[
                random_int(0, $maxIndex)
            ];
        }

        return self::PREFIX . $suffix;
    }
}