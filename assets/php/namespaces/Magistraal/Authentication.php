<?php 
    namespace Magistraal\Authentication;

    function generate_client_id() {
        return 'M6LOAPP';
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
                "UPDATE `magistraal_tokens` SET `access_token`=?, `access_token_expires`=?, `refresh_token`=? WHERE `token_id`=?",
                [$access_token, $args['access_token_expires'], $refresh_token, $token_id]
            );
        } else {
            $token_id      = \Magistraal\Authentication\random_token_id();
            $token_expires = time() + 2700; // Token will expire in 45 minutes

            $res = \Magistraal\Database\query(
                "INSERT INTO `magistraal_tokens` (`token_id`, `token_expires`, `tenant`, `access_token`, `access_token_expires`, `refresh_token`, `ip_address`, `user_uuid`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$token_id, $token_expires, $args['tenant'], $access_token, $args['access_token_expires'], $refresh_token, $ip_address, $args['user_uuid']]
            );
        }
        
        if($res) {
            return $token_id;
        }

        \Magistraal\Response\error('error_storing_tokena');
    }
    
    function token_get($token_id, $check_token_expiry = true) {
        $rows = \Magistraal\Database\query("SELECT * FROM `magistraal_tokens` WHERE `token_id`=?", $token_id);
        if(!isset($rows[0])) {
            return null;
        }
        $token_data = $rows[0];

        $token_data['access_token']  = \Magistraal\Encryption\decrypt($token_data['access_token']);
        $token_data['refresh_token'] = \Magistraal\Encryption\decrypt($token_data['refresh_token']);

        if($check_token_expiry && time() > $token_data['token_expires']) {
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
        $rows = \Magistraal\Database\query("DELETE FROM `magistraal_tokens` WHERE `token_id`=?", $token_id);
        
        return ($rows > 0);
    }

    function random_token_id($length= 128) {
        $token_id = substr(base64_url_encode(random_bytes($length)), 0, $length);

        return $token_id;
    }
?>