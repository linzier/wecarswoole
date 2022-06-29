<?php

namespace WecarSwoole\Util;

class Encrypt
{
    public static function enc(string $txt, string $secret): string
    {
        $enc = openssl_encrypt($txt, "AES-128-CBC", $secret, OPENSSL_RAW_DATA);
        if (!$enc) {
            return '';
        }

        return base64_encode($enc);
    }

    public static function dec(string $txt, string $secret): string
    {
        $dec = openssl_decrypt(base64_decode($txt), "AES-128-CBC", $secret, OPENSSL_RAW_DATA);
        if (!$dec) {
            return '';
        }

        return $dec;
    }
}
