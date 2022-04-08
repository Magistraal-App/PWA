<?php 
    namespace Magistraal\Encryption;

    function encrypt($decrypted) {
        $iv_len         = openssl_cipher_iv_length($_ENV['ENCRYPT_CIPHER']);
        $iv             = openssl_random_pseudo_bytes($iv_len);
        $ciphertext_raw = openssl_encrypt($decrypted, $_ENV['ENCRYPT_CIPHER'], $_ENV['ENCRYPT_KEY'], $options=OPENSSL_RAW_DATA, $iv);
        $hmac           = hash_hmac('sha256', $ciphertext_raw, $_ENV['ENCRYPT_KEY'], true);
        $encrypted      = base64_encode($iv.$hmac.$ciphertext_raw);

        return $encrypted;
    }

    function decrypt($encrypted) {
        $encrypted = base64_decode($encrypted);
        $iv_len = openssl_cipher_iv_length($_ENV['ENCRYPT_CIPHER']);
        $iv = substr($encrypted, 0, $iv_len);
        $hmac = substr($encrypted, $iv_len, 32);
        $ciphertext_raw = substr($encrypted, $iv_len + 32);
        $decrypted = openssl_decrypt($ciphertext_raw, $_ENV['ENCRYPT_CIPHER'], $_ENV['ENCRYPT_KEY'], $options=OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $_ENV['ENCRYPT_KEY'], true);
        if (hash_equals($hmac, $calcmac)) {
            return $decrypted;
        }
    }
?>