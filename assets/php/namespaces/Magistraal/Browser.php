<?php 
    namespace Magistraal\Browser;

    class Browser {
        public static $cookies = [];

        public static function request($url, $options = []) {
            if(!isset($options['payload']) || empty($options['payload']) && !isset($options['method'])) {
                $options['method'] = 'GET';
            }

            $options = array_replace([
                'payload'   => [],
                'method'    => 'POST',
                'cookie'    => \Magistraal\Browser\encode_cookie_header(\Magistraal\Browser\Browser::$cookies),
                'anonymous' => false
            ], $options);

            $options['headers'] = array_replace([
                'origin'         => \Magistraal\Config\get('domain_accounts'),
                'referrer'       => \Magistraal\Config\get('domain_accounts'),
                'sec-fetch-dest' => 'empty',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-site' => 'same-origin',
                'user-agent'     => \Magistraal\Config\get('default_user_agent'),
                'content-type'   => 'application/json',
                'cache-control'  => 'no-cache'
            ], $options['headers'] ?? []);

            // Set cookie header
            if(!empty(\Magistraal\Browser\Browser::$cookies)) {
                $options['headers']['cookie'] = \Magistraal\Browser\encode_cookie_header(\Magistraal\Browser\Browser::$cookies);
            }

            if(!$options['anonymous']) {
                if(isset(\Magister\Session::$accessToken)) {
                    // Set authorization header
                    $options['headers']['authorization'] = 'Bearer '.\Magister\Session::$accessToken;
                } else if(isset(\Magistraal\Browser\Browser::$cookies['XSRF-TOKEN'])) {
                    // Set x-xsrf-token header
                    $options['headers']['x-xsrf-token'] = \Magistraal\Browser\Browser::$cookies['XSRF-TOKEN'];
                }
            }

            foreach ($options['headers'] as $key => $value) {
                $headers[] = "{$key}: {$value}";
            }

            // echo($url."\n\n");
            // print_r($options['headers']);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_POST, false);
            if(!empty($options['payload'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['payload']));
            }
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($options['method']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response    = curl_exec($ch);
            $info        = curl_getinfo($ch);
            $headers_str = substr($response, 0, strpos($response, "\r\n\r\n"));
            $body        = trim(str_split_pos($response, strpos($response, "\r\n\r\n"))[1]);
            $headers     = \Magistraal\Browser\decode_headers($headers_str);
            
            if(isset($headers['content-type']) && strpos($headers['content-type'], 'application/json') !== false) {
                $body = json_decode($body, true);
            }

            // Store cookies
            $cookies = \Magistraal\Browser\decode_cookie_header($headers['set-cookie'] ?? null);
            foreach ($cookies as $cookie_key => $cookie_data) {
                if($cookie_data['expires'] <= time() && isset(\Magistraal\Browser\Browser::$cookies[$cookie_key])) {
                    unset(\Magistraal\Browser\Browser::$cookies[$cookie_key]);
                    continue;
                }

                \Magistraal\Browser\Browser::$cookies[$cookie_key] = $cookie_data['value'];
            }

            // Redirect
            if(isset($headers['location'])) {
                $new_url = $headers['location'];
                if(strpos($new_url, '/') === 0) { // Relative url on same domain
                    $new_url = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).$new_url;
                }

                return \Magistraal\Browser\Browser::request($new_url, $options);
            }

            return [
                'headers' => $headers,
                'body'    => $body,
                'info'    => $info,
                'options' => $options
            ];
        }
    }

    function decode_headers($headers_str) {
        $headers = [];
        $headers_arr = explode("\r\n", $headers_str);
        foreach ($headers_arr as $i => $header_str) {
            if($i === 0) {
                if(strpos($header_str, 'HTTP') === 0) {
                    $headers['http_code'] = trim(str_split_pos($header_str, strpos($header_str, ' '))[1]);
                }
            }

            $colons = strpos_all($header_str, ':');
            if(!isset($colons[0])) {
                continue;
            }

            $split_pos = $colons[0] === 0 && isset($colons[1]) ? $colons[1] : $colons[0];

            list($header_key, $header_value) = str_split_pos($header_str, $split_pos);

            $header_key = strtolower($header_key); // Convert header key to lowercase
            $header_value = substr($header_value, 2); // Remove space and colon

            if(isset($headers[$header_key])) {
                // Turn into array if header was sent multiple times (i.e. set-cookie)
                if(!is_array($headers[$header_key])) {
                    $headers[$header_key] = [$headers[$header_key]];
                }
                array_push($headers[$header_key], $header_value);
            } else {
                $headers[$header_key] = $header_value;
            }
        }

        return $headers;
    }

    function decode_cookie_header($cookie_header) {
        $cookies = [];

        if(!is_array($cookie_header)) {
            $cookie_header = [$cookie_header];
        }

        foreach ($cookie_header as $cookie) {
            parse_str(str_replace(['; ', ';'], '&', $cookie), $cookie_data);

            if(count($cookie_data) < 1) {
                continue;
            }

            $cookie_key   = array_key_first($cookie_data);
            $cookie_value = $cookie_data[$cookie_key];

            if(isset($cookie['expires'])) {
                $cookie_data['expires'] = strtotime($cookie['expires']);
            } else {
                $cookie_data['expires'] = time() + 365*24*60*60;
            }

            if(is_null($cookie_value)) {
                $cookie_data['expires'] = 0;
            }

            $cookies[$cookie_key] = [
                'value'   => $cookie_value,
                'expires' => $cookie_data['expires']
            ];
        }

        return $cookies;
    }

    function encode_cookie_header($cookies) {
        $cookie_header = '';

        foreach ($cookies as $cookie_key => $cookie_value) {
            $cookie_key = str_replace('_', '.', $cookie_key);
            $cookie_header .= "{$cookie_key}={$cookie_value}; ";
        }

        return rtrim($cookie_header, ' ');
    }
?>