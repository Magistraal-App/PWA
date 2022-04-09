<?php 
    namespace Magistraal\Encryption;

    function encrypt($pure_string) {
        $options    = OPENSSL_RAW_DATA;
        $hash_algo  = 'sha256';
        $sha2len    = 32;
        $ivlen = openssl_cipher_iv_length($_ENV['ENCRYPT_CIPHER']);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($pure_string, $_ENV['ENCRYPT_CIPHER'], $_ENV['ENCRYPT_KEY'], $options, $iv);
        $hmac = hash_hmac($hash_algo, $ciphertext_raw, $_ENV['ENCRYPT_KEY'], true);
        return base64_encode($iv.$hmac.$ciphertext_raw);
    }

    function decrypt($encrypted_string) {
        $encrypted_string = base64_decode($encrypted_string);
        $options    = OPENSSL_RAW_DATA;
        $hash_algo  = 'sha256';
        $sha2len    = 32;
        $ivlen = openssl_cipher_iv_length($_ENV['ENCRYPT_CIPHER']);
        $iv = substr($encrypted_string, 0, $ivlen);
        $hmac = substr($encrypted_string, $ivlen, $sha2len);
        $ciphertext_raw = substr($encrypted_string, $ivlen+$sha2len);
        $original_plaintext = openssl_decrypt($ciphertext_raw, $_ENV['ENCRYPT_CIPHER'], $_ENV['ENCRYPT_KEY'], $options, $iv);
        $calcmac = hash_hmac($hash_algo, $ciphertext_raw, $_ENV['ENCRYPT_KEY'], true);
       
        if(@hash_equals($hmac, $calcmac)) {
            return $original_plaintext;
        }
    }

    // function encrypt($decrypted) {
    //     $options        = OPENSSL_RAW_DATA;
    //     $hash_algo      = 'sha256';
    //     $iv_len         = openssl_cipher_iv_length($_ENV['ENCRYPT_CIPHER']);
    //     $iv             = openssl_random_pseudo_bytes($iv_len);
    //     $ciphertext_raw = openssl_encrypt($decrypted, $_ENV['ENCRYPT_CIPHER'], $_ENV['ENCRYPT_KEY'], $options, $iv);
    //     $hmac           = hash_hmac($hash_algo, $ciphertext_raw, $_ENV['ENCRYPT_KEY'], true);
    //     $encrypted      = base64_encode($iv.$hmac.$ciphertext_raw);

    //     return $encrypted;
    // }

    // function decrypt($encrypted) {
    //     $encrypted      = base64_decode($encrypted);
    //     $options        = OPENSSL_RAW_DATA;
    //     $hash_algo      = 'sha256';
    //     $hmac_len       = 32;
    //     $iv_len         = openssl_cipher_iv_length($_ENV['ENCRYPT_CIPHER']);
    //     $iv             = substr($encrypted, 0, $iv_len);
    //     $hmac           = substr($encrypted, $iv_len, $hmac_len);
    //     $ciphertext_raw = substr($encrypted, $iv_len + $hmac_len);
    //     $decrypted      = openssl_decrypt($ciphertext_raw, $_ENV['ENCRYPT_CIPHER'], $_ENV['ENCRYPT_KEY'], $options, $iv);
    //     $calcmac        = hash_hmac($hash_algo, $ciphertext_raw, $_ENV['ENCRYPT_KEY'], true);
        
    //     if(@hash_equals($hmac, $calcmac)) {
    //         return $decrypted;
    //     }
    // }
?>