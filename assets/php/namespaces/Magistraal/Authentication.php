<?php 
    namespace Magistraal\Authentication;

    function generate_client_id() {
        return 'M6LOAPP';
    }

    function generate_login_path() {
        $nonce            = \Magistraal\Authentication\generate_nonce();
        $state            = \Magistraal\Authentication\generate_state();

        $code_verifier    = \Magistraal\Authentication\generate_code_verifier();
        $code_challenge   = \Magistraal\Authentication\generate_code_challenge($code_verifier);

        \Magister\Session::$codeChallenge = $code_challenge;
        \Magister\Session::$codeVerifier  = $code_verifier;
        
        return "/connect/authorize?client_id=M6LOAPP&redirect_uri=m6loapp%3A%2F%2Foauth2redirect%2F&scope=openid%20profile%20offline_access%20magister.mobile%20magister.ecs&response_type=code%20id_token&state={$state}&nonce={$nonce}&code_challenge={$code_challenge}&code_challenge_method=S256";
    }

    function generate_nonce() {
        return random_hex(32);
    }

    function generate_state() {
        return random_hex(32);
    }

    function generate_code_verifier() {
        return random_str(128);
    }

    function generate_code_challenge($code_verifier) {
        return base64_url_encode(pack('H*', hash('sha256', $code_verifier)));
    }

    /* ============================ */
    /*            TOKENS            */
    /* ============================ */

    function token_put($args = [], $token_id = null) {
        $access_token  = \Magistraal\Encryption\encrypt($args['access_token']);
        $refresh_token = \Magistraal\Encryption\encrypt($args['refresh_token']);
        $ip_address    = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;

        if(isset($token_id)) {
            $res = \Magistraal\Database\query(
                "UPDATE magistraal_tokens SET access_token=?, access_token_expires=?, refresh_token=? WHERE token_id=?",
                [$access_token, $args['access_token_expires'], $refresh_token, $token_id]
            );
        } else {
            $token_id      = \Magistraal\Authentication\random_token_id();
            $token_expires = time() + 2700; // Token will expire in 45 minutes

            $res = \Magistraal\Database\query(
                "INSERT INTO magistraal_tokens (token_id, token_expires, tenant, access_token, access_token_expires, refresh_token, ip_address, user_uuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$token_id, $token_expires, $args['tenant'], $access_token, $args['access_token_expires'], $refresh_token, $ip_address, $args['user_uuid']]
            );
        }
        
        if($res) {
            return $token_id;
        }

        \Magistraal\Response\error('error_storing_tokena');
    }
    
    function token_set_user_uuid($token_id, $user_uuid) {
        if(isset($token_id) && isset($user_uuid)) {
            \Magistraal\Database\query("UPDATE magistraal_tokens SET user_uuid=? WHERE token_id=?", [$user_uuid, $token_id]);
            return $token_id;
        }

        \Magistraal\Response\error('error_setting_token_user_uuid');
    }

    function token_get($token_id) {
        $rows = \Magistraal\Database\query("SELECT * FROM magistraal_tokens WHERE token_id=?", $token_id);

        if(!isset($rows[0])) {
            return null;
        }
        $token_data = $rows[0];

        $token_data['access_token']  = \Magistraal\Encryption\decrypt($token_data['access_token']);
        $token_data['refresh_token'] = \Magistraal\Encryption\decrypt($token_data['refresh_token']);

        if(time() > $token_data['token_expires']) {
            // $new_token_id = \Magistraal\Authentication\random_token_id();
            // $new_token_expires = time()

            // // Rename token id and change expiry
            // \Magistraal\Database\query("UPDATE magistraal_tokens SET token_id=? WHERE token_id=?", [$new_token_id, $token_id]);

            // var_dump($token_id, $new_token_id);

            // Delete current token 
            \Magistraal\Authentication\token_delete($token_id);
            
            // Create new token
            $new_token_id = \Magistraal\Authentication\token_put($token_data);

            // Overwrite token data to new token's data
            $token_data = \Magistraal\Authentication\token_get($new_token_id);
        
            setcookie('magistraal-authorization', $new_token_id, time()+365*24*60*60, '/magistraal/');
        }

        return $token_data;
    }

    function token_delete($token_id) {
        $rows = \Magistraal\Database\query("DELETE FROM magistraal_tokens WHERE token_id=?", $token_id);
        
        return ($rows > 0);
    }

    function random_token_id($length= 64) {
        $token_id = substr(base64_url_encode(random_bytes($length)), 0, $length);

        return $token_id;
    }
?>