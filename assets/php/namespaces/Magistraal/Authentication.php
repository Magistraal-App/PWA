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

    function token_put($args = []) {
        $db = \Magistraal\Database\connect();

        $token_id      = \Magistraal\Authentication\random_token_id();
        $token_expires = time() + 900; // Token will expire in 15 minutes
        $user_agent    = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $access_token  = \Magistraal\Encryption\encrypt($args['access_token']);
        $refresh_token = \Magistraal\Encryption\encrypt($args['refresh_token']);

        if($db->q("INSERT INTO magistraal_tokens 
        (token_id,      token_expires,      tenant,              access_token,      access_token_expires,              refresh_token,      user_agent) VALUES 
        ('{$token_id}', '{$token_expires}', '{$args['tenant']}', '{$access_token}', '{$args['access_token_expires']}', '{$refresh_token}', '{$user_agent}')")) {
            return $token_id;
        }

        \Magistraal\Response\error('error_storing_token');
    }

    function token_get($token_id) {
        $db = \Magistraal\Database\connect();
        $token_data = $db->q("SELECT * FROM magistraal_tokens where token_id='{$token_id}'");

        if(!isset($token_data[0])) {
            return null;
        }
        $token_data = $token_data[0];

        if($_SERVER['HTTP_USER_AGENT'] != $token_data['user_agent'] && $token_data['user_agent'] != '') {
            \Magistraal\Authentication\token_delete($token_id);
            return false;
        }

        $token_data['access_token']  = \Magistraal\Encryption\decrypt($token_data['access_token']);
        $token_data['refresh_token'] = \Magistraal\Encryption\decrypt($token_data['refresh_token']);

        if(time() > $token_data['token_expires']) {
            // Generate new token if it has expired
            \Magistraal\Authentication\token_delete($token_id);

            $new_token_id = \Magistraal\Authentication\token_put([
                'tenant'               => $token_data['tenant'],
                'access_token'         => $token_data['access_token'],
                'access_token_expires' => $token_data['access_token_expires'],
                'refresh_token'        => $token_data['refresh_token']
            ]);

            // $token_data = \Magistraal\Authentication\token_get($new_token_id);
        
            header("x-auth-token: {$new_token_id}");
        }

        return $token_data;
    }

    function token_delete($token_id) {
        $token_file = ROOT."/data/tokens/{$token_id}.json";

        if(!file_exists($token_file)) {
            return false;
        }

        return unlink($token_file);
    }

    function random_token_id($length= 64) {
        $token_id = substr(base64_url_encode(random_bytes($length)), 0, $length);

        return $token_id;
    }
?>