<?php

class Nip_Hash
{
    protected static $golden_primes = [
        36 => [1, 23, 809, 28837, 1038073, 37370257, 1345328833],
    ];

    public static function uhash($num, $len = 5, $base = 36)
    {
        $gp = self::$golden_primes[$base];
        $maxlen = count($gp);
        $len = $len > ($maxlen - 1) ? ($maxlen - 1) : $len;
        while ($len < $maxlen && pow($base, $len) < $num) {
            $len++;
        }
        if ($len >= $maxlen) {
            throw new Exception($num . ' out of range (max '.pow($base, $maxlen - 1).')');
        }
        }
        $ceil = pow($base, $len);
        $prime = $gp[$len];
        $dechash = ($num * $prime) % $ceil;
        $hash = base_convert($dechash, 10, $base);
        return str_pad($hash, $len, '0', STR_PAD_LEFT);
    }
}
