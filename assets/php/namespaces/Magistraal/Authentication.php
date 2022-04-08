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

        print_r('Code verifier: '.$code_verifier."\n");
        print_r('Code challenge: '.$code_challenge."\n\n\n");
        
        return "/connect/authorize?client_id=M6LOAPP&redirect_uri=m6loapp%3A%2F%2Foauth2redirect%2F&scope=openid%20profile%20offline_access%20magister.mobile%20magister.ecs&response_type=code%20id_token&state={$state}&nonce={$nonce}&code_challenge={$code_challenge}&code_challenge_method=S256";
    }

    function generate_nonce() {
        return random_hex(32);
    }

    function generate_state() {
        return random_hex(32);
    }

    function generate_code_verifier() {
        return random_base64(128);
    }

    function generate_code_challenge($code_verifier) {
        return base64_url_encode(hash('sha256', $code_verifier));
    }

    /* ============================ */
    /*            TOKENS            */
    /* ============================ */

    function token_put($args = []) {
        $token_id   = $args['token'] ?? \Magistraal\Authentication\random_token_id();
        $token_file = ROOT."/data/tokens/{$token_id}.json";
        
        $token_data = [
            'tenant'               => $args['tenant'],
            'access_token'         => $args['access_token'] ?? null,
            'access_token_expires' => $args['bearer_expires'] ?? null,
            'refresh_token'        => $args['refresh_token'] ?? null,
            'user_agent'           => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires'              => time() + 500000 // Expires in 15 minutes
        ];

        file_put_contents($token_file, json_encode($token_data));

        return $token_id;
    }

    function token_get($token_id) {
        $token_file = ROOT."/data/tokens/{$token_id}.json";
        if(!file_exists($token_file)) {
            return false;
        }

        if(($token_data = @json_decode(@file_get_contents($token_file), true)) === null) {
            return false;
        }

        if($_SERVER['HTTP_USER_AGENT'] != $token_data['user_agent'] && $token_data['user_agent'] != '') {
            \Magistraal\Authentication\token_delete($token_id);
            return false;
        }

        $token_data = array_replace([
            'tenant'         => '',
            'username'       => '',
            'password'       => '',
            'user_agent'     => '',
            'bearer'         => '',
            'bearer_expires' => 0,
            'expires'        => 0
        ], $token_data);

        if(time() > $token_data['expires']) {
            \Magistraal\Authentication\token_delete($token_id);

            $new_token_id = \Magistraal\Authentication\random_token_id();
            \Magistraal\Authentication\token_put($new_token_id, $token_data['tenant'], $token_data['username'], $token_data['password'], $token_data['user_agent'], $token_data['bearer'], $token_data['bearer_expires']);
        
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

    function random_token_id() {
        $length = 64;
        $str    = str_replace(['/', '+'], ['_', '-'], base64_encode(random_bytes($length)));
        return substr($str, 0, $length);
    }
?>