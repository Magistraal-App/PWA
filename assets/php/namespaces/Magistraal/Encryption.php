<?php 
    namespace Magistraal\Encryption;

    function encrypt($str, $encrypt_key_index = 0) {
        $iv_len      = openssl_cipher_iv_length($_ENV['ENCRYPT_CIPHER']);
        $tag_length  = 16;
        $iv          = openssl_random_pseudo_bytes($iv_len);
        $tag         = '';
        $encrypt_key = $_ENV['ENCRYPT_KEYS'][$encrypt_key_index] ?? $_ENV['ENCRYPT_KEYS'][0];

        $ciphertext  = openssl_encrypt($str, $_ENV['ENCRYPT_CIPHER'], $encrypt_key, OPENSSL_RAW_DATA, $iv, $tag, '', $tag_length);
        $encrypted   = base64_encode($iv.$ciphertext.$tag);

        return $encrypted;
    }

    function decrypt($encrypted, $encrypt_key_index = 0) {
        $encrypted   = base64_decode($encrypted);
        $iv_len      = openssl_cipher_iv_length($_ENV['ENCRYPT_CIPHER']);
        $tag_length  = 16;
        $iv          = substr($encrypted, 0, $iv_len);
        $ciphertext  = substr($encrypted, $iv_len, -$tag_length);
        $tag         = substr($encrypted, -$tag_length);

        $encrypt_key = $_ENV['ENCRYPT_KEYS'][$encrypt_key_index] ?? $_ENV['ENCRYPT_KEYS'][0];
        $decrypted   = openssl_decrypt($ciphertext, $_ENV['ENCRYPT_CIPHER'], $encrypt_key, OPENSSL_RAW_DATA, $iv, $tag);
    
        return $decrypted;
    }
?>