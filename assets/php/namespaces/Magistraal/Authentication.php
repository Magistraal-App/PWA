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
        $db = \Magistraal\Database\connect();

        $access_token  = \Magistraal\Encryption\encrypt($args['access_token']);
        $refresh_token = \Magistraal\Encryption\encrypt($args['refresh_token']);
        $ip_address    = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;

        if(isset($token_id)) {
            $response = $db->q("UPDATE magistraal_tokens 
                                SET access_token='{$access_token}', access_token_expires='{$args['access_token_expires']}', refresh_token='{$refresh_token}' 
                                WHERE token_id='{$token_id}'");
        } else {
            $token_id      = \Magistraal\Authentication\random_token_id();
            $token_expires = time() + 900000; // Token will expire in 15 minutes

            $response = $db->q("INSERT INTO magistraal_tokens 
                (token_id,      token_expires,      tenant,              access_token,      access_token_expires,              refresh_token,      ip_address) VALUES 
                ('{$token_id}', '{$token_expires}', '{$args['tenant']}', '{$access_token}', '{$args['access_token_expires']}', '{$refresh_token}', '{$ip_address}')");
        }
        
        if($response) {
            return $token_id;
        }

        \Magistraal\Response\error('error_storing_token');
    }

    function token_get($token_id) {
        $db = \Magistraal\Database\connect();
        $response = $db->q("SELECT * FROM magistraal_tokens WHERE token_id='{$token_id}'");

        if(!isset($response[0])) {
            return null;
        }
        $token_data = $response[0];

        $token_data['access_token']  = \Magistraal\Encryption\decrypt($token_data['access_token']);
        $token_data['refresh_token'] = \Magistraal\Encryption\decrypt($token_data['refresh_token']);

        if(time() > $token_data['token_expires']) {
            // Delete old token
            \Magistraal\Authentication\token_delete($token_id);

            // Create new token
            $new_token_id = \Magistraal\Authentication\token_put([
                'tenant'               => $token_data['tenant'],
                'access_token'         => $token_data['access_token'],
                'access_token_expires' => $token_data['access_token_expires'],
                'refresh_token'        => $token_data['refresh_token']
            ]);

            $token_data = \Magistraal\Authentication\token_get($new_token_id);
        
            header("X-Auth-Token: {$new_token_id}");
        }

        return $token_data;
    }

    function token_delete($token_id) {
        $db = \Magistraal\Database\connect();
        $response = $db->q("DELETE FROM magistraal_tokens WHERE token_id='{$token_id}';");
        
        return $response;
    }

    function random_token_id($length= 64) {
        $token_id = substr(base64_url_encode(random_bytes($length)), 0, $length);

        return $token_id;
    }
?>