<?php

namespace WecarSwoole\Util;

class Encrypt
{
    public static function enc(string $txt, string $secret): string
    {
        $len = openssl_cipher_iv_length("AES-128-CBC");
        $iv = str_pad(substr($secret, 0, $len), $len, '0');
        $enc = openssl_encrypt($txt, "AES-128-CBC", $secret, OPENSSL_RAW_DATA, $iv);
        if (!$enc) {
            return '';
        }

        return base64_encode($enc);
    }

    public static function dec(string $txt, string $secret): string
    {
        $len = openssl_cipher_iv_length("AES-128-CBC");
        $iv = str_pad(substr($secret, 0, $len), $len, '0');
        $dec = openssl_decrypt(base64_decode($txt), "AES-128-CBC", $secret, OPENSSL_RAW_DATA, $iv);
        if (!$dec) {
            return '';
        }

        return $dec;
    }
}
