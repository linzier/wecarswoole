<?php

namespace WecarSwoole\Util;

class Encrypt
{
    public static function enc(string $txt, string $secret): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("AES-128-CBC"));
        $enc = openssl_encrypt($txt, "AES-128-CBC", $secret, OPENSSL_RAW_DATA, $iv);
        if (!$enc) {
            return '';
        }

        return base64_encode($enc);
    }

    public static function dec(string $txt, string $secret): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("AES-128-CBC"));
        $dec = openssl_decrypt(base64_decode($txt), "AES-128-CBC", $secret, OPENSSL_RAW_DATA, $iv);
        if (!$dec) {
            return '';
        }

        return $dec;
    }
}
