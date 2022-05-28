<?php 
    namespace Magister;

    class Session {
        public static $authCode;
        public static $accessToken;
        public static $accessTokenExpires;
        public static $domain;
        public static $returnUrl;
        public static $refreshToken;
        public static $sessionId;
        public static $tenantId;
        public static $tokenId;
        public static $userId;
        public static $userUuid;
        public static $codeVerifier;
        public static $codeChallenge;
        public static $checkTokenExpiry;

        public static function start($token_id = null, $check_token_expiry = true) {
            $token_id = $token_id ?? $_COOKIE['magistraal-authorization'] ?? null;

            \Magister\Session::$checkTokenExpiry = $check_token_expiry;

            if(!isset($token_id)) {
                \Magistraal\Response\error('token_invalid_not_sent');
            }
            
            return \Magister\Session::loginToken($token_id);
        }

        public static function login($tenant_id, $username, $password) {
            \Magister\Session::$tenantId = $tenant_id;
            
            // Get login endpoint
            list($login_url, \Magister\Session::$codeVerifier, \Magister\Session::$codeChallenge) = \Magister\Session::getLoginEndpoint();

            // Get actual login page url
            $response  = \Magistraal\Api\call('https://accounts.magister.net'.$login_url);
            $login_url = $response['info']['url'];
            parse_str(parse_url($login_url)['query'], $login_url_query);

            // Store login information
            \Magister\Session::$sessionId = $login_url_query['sessionId'] ?? \Magistraal\Response\error('error_getting_session_id');
            \Magister\Session::$returnUrl = $login_url_query['returnUrl'] ?? \Magistraal\Response\error('error_getting_return_url');
            \Magister\Session::$authCode  = \Magister\Session::getAuthCode() ?? \Magistraal\Response\error('error_getting_authcode');
            
            // Enter tenant
            if(!\Magister\Session::loginTenant($tenant_id)) {
                return ['success' => false, 'info' => 'login_field_incorrect.tenant'];
            }

            // Enter username
            if(!\Magister\Session::loginUsername($username)) {
                return ['success' => false, 'info' => 'login_field_incorrect.username'];
            }

            // Enter password
            if(!\Magister\Session::loginPassword($password)) {
                return ['success' => false, 'info' => 'login_field_incorrect.password'];
            }

            // Get bearer
            $bearer = \Magister\Session::getBearerTokens(\Magister\Session::$codeVerifier);

            // Store bearer in session
            \Magister\Session::$accessToken        = $bearer['access_token'];
            \Magister\Session::$accessTokenExpires = time() + $bearer['expires_in'];
            \Magister\Session::$refreshToken       = $bearer['refresh_token'];

            // Get domain
            \Magister\Session::$domain = \Magister\Session::getDomain();

            // Get user info
            list(\Magister\Session::$userId, \Magister\Session::$userUuid) = \Magister\Session::getUserInfo();

            // Save token data
            $token_id = \Magistraal\Authentication\token_put([
                'tenant'               => \Magister\Session::$tenantId ?? null,
                'access_token'         => \Magister\Session::$accessToken ?? null,
                'access_token_expires' => \Magister\Session::$accessTokenExpires ?? null,
                'refresh_token'        => \Magister\Session::$refreshToken ?? null,
                'user_uuid'            => \Magister\Session::$userUuid ?? null
            ]);
            
            return ['success' => true, 'token_id' => $token_id ?? null, 'user_uuid' => \Magister\Session::$userUuid ?? null];
        }

        public static function getDomain() {
            $res              = \Magistraal\Api\call('https://magister.net/.well-known/host-meta.json');
            $tenant_api_url   = $res['body']['links'][1]['href'] ?? \Magistraal\Response\error('error_obtaining_tenant_url');
            $tenant_subdomain = explode('.', parse_url($tenant_api_url, PHP_URL_HOST))[0];

            return "https://{$tenant_subdomain}.magister.net";
        }

        public static function loginToken($token_id) {
            $token_data = \Magister\Session::getTokenData($token_id);

            \Magister\Session::$tenantId = $token_data['tenant'] ?? null;

            if(!isset($token_data) || empty($token_data)) {
                \Magistraal\Response\error('token_invalid_not_found');
                return false;
            }

            // Store bearer in session
            \Magister\Session::$accessToken        = $token_data['access_token'];
            \Magister\Session::$accessTokenExpires = $token_data['access_token_expires'];
            \Magister\Session::$refreshToken       = $token_data['refresh_token'];

            // Get domain
            \Magister\Session::$domain = \Magister\Session::getDomain();

            // Get user info
            list(\Magister\Session::$userId, \Magister\Session::$userUuid) = \Magister\Session::getUserInfo();

            return true;
        }

        public static function loginTenant($tenant_id) {
            $res = \Magistraal\Api\call('https://accounts.magister.net/challenges/tenant/', [
                'authCode'  => \Magister\Session::$authCode,
                'returnUrl' => \Magister\Session::$returnUrl,
                'sessionId' => \Magister\Session::$sessionId,
                'tenant'    => $tenant_id
            ]);

            return strpos($res['info']['url'], '/error') === false;
        }

        public static function loginUsername($username) {
            $res = \Magistraal\Api\call('https://accounts.magister.net/challenges/username/', [
                'authCode'  => \Magister\Session::$authCode,
                'returnUrl' => \Magister\Session::$returnUrl,
                'sessionId' => \Magister\Session::$sessionId,
                'username'  => $username
            ]);

            return $res['info']['success'];
        }

        public static function loginPassword($password) {
            $res = \Magistraal\Api\call('https://accounts.magister.net/challenges/password/', [
                'authCode'  => \Magister\Session::$authCode,
                'returnUrl' => \Magister\Session::$returnUrl,
                'sessionId' => \Magister\Session::$sessionId,
                'password'  => $password
            ]);

            return $res['info']['success'];
        }

        public static function getTokenData($token_id) {
            // Get token data
            $token_data = \Magistraal\Authentication\token_get($token_id, \Magister\Session::$checkTokenExpiry) ?? null;

            // Update $token_id as it might have changed
            $token_id = $token_data['token_id'] ?? $token_id;

            if(!isset($token_data) || empty($token_data)) {
                // The token does not exist, return
                return null;
            }
                
            if(isset($token_data['access_token_expires']) && ($token_data['access_token_expires'] - 900) > time()) {
                // Use the current token as the access token won't expire soon
                $res_token_data = $token_data;
            } else {
                // Update token data if the access token expires soon

                // Refresh bearer
                $bearer = \Magister\Session::getRefreshedBearer($token_data['refresh_token']);

                if(isset($bearer['error'])) {
                    \Magistraal\Response\error('token_invalid_'.$bearer['error']);
                    return false;
                }
                
                // New token data (inherit tenant and user_uuid)
                $new_token_data = [
                    'tenant'               => $token_data['tenant'],
                    'access_token'         => $bearer['access_token'],
                    'access_token_expires' => time() + $bearer['expires_in'],
                    'refresh_token'        => $bearer['refresh_token'],
                    'user_uuid'            => $token_data['user_uuid']
                ];

                // Store new token data
                \Magistraal\Authentication\token_put($new_token_data, $token_id);

                $res_token_data = $new_token_data;
            }
            
            return [
                'token_id'             => $token_id,
                'access_token'         => $res_token_data['access_token'] ?? \Magistraal\Response\error('failed_to_obtain_access_token'),
                'access_token_expires' => $res_token_data['access_token_expires'] ?? \Magistraal\Response\error('failed_to_obtain_access_token_expires'),
                'refresh_token'        => $res_token_data['refresh_token'] ?? \Magistraal\Response\error('failed_to_obtain_refresh_token'),
                'tenant'               => $res_token_data['tenant'] ?? \Magistraal\Response\error('failed_to_obtain_token_data_tenant')
            ];
        }

        private static function getLoginEndpoint() {
            $nonce            = \Magistraal\Authentication\generate_nonce();
            $state            = \Magistraal\Authentication\generate_state();
            $code_verifier    = \Magistraal\Authentication\generate_code_verifier();
            $code_challenge   = \Magistraal\Authentication\generate_code_challenge($code_verifier);
            
            return [
                /* Url */            "/connect/authorize?client_id=M6LOAPP&redirect_uri=m6loapp%3A%2F%2Foauth2redirect%2F&scope=openid%20profile%20offline_access%20magister.mobile%20magister.ecs&response_type=code%20id_token&state={$state}&nonce={$nonce}&code_challenge={$code_challenge}&code_challenge_method=S256",
                /* Code verifier */  $code_verifier,
                /* Code challenge */ $code_challenge
            ];
        }

        private static function getBearer($refresh_token) {
            if(!isset($refresh_token)) {
                \Magistraal\Response\error('bearer_refresh_token_not_supplied');
            }


        }

        private static function getOpenIdCode($return_url) {
            $redirect_uri = \Magistraal\Api\call("https://accounts.magister.net{$return_url}")['info']['url'] ?? null;

            parse_str(parse_url($redirect_uri, PHP_URL_FRAGMENT), $openid);

            if(!isset($openid['code'])) {
                \Magistraal\Response\error('error_getting_openid_code');
            }

            return $openid['code'];
        }

        private static function getBearerTokens($code_verifier) {
            // Get OpenId code
            $openid_code = \Magister\Session::getOpenIdCode(\Magister\Session::$returnUrl);

            // Get refresh and access token
            $res = \Magistraal\Browser\Browser::request('https://accounts.magister.net/connect/token', [
                'headers' => [
                    'x-api-client-id' => 'EF15',
                    'content-type'    => 'application/x-www-form-urlencoded',
                    'host'            => 'accounts.magister.net'
                ],
                'payload' => [
                    'client_id' => 'M6LOAPP',
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => 'm6loapp://oauth2redirect/',
                    'code' => $openid_code,
                    'code_verifier' => $code_verifier
                ]
            ])['body'] ?? null; 

            return $res;
        }

        private static function getRefreshedBearer($refresh_token) {
            $bearer = \Magistraal\Browser\Browser::request('https://accounts.magister.net/connect/token', [
                'headers' => [
                    'x-api-client-id' => 'EF15',
                    'content-type'    => 'application/x-www-form-urlencoded',
                    'host'            => 'accounts.magister.net'
                ],
                'payload' => [
                    'refresh_token' => $refresh_token,
                    'client_id'     => 'M6LOAPP',
                    'grant_type'    => 'refresh_token'
                ]
            ])['body'] ?? null;

            return $bearer;
        }

        public static function getUserInfo() {
            $res = \Magistraal\Api\call(\Magister\Session::$domain.'/api/account');
            
            return [
                /* User id */   $res['body']['Persoon']['Id'] ?? \Magistraal\Response\error('failed_to_obtain_user_id'),
                /* User uuid */ str_replace('-', '', $res['body']['UuId'] ?? null).'-'.\Magister\Session::$tenantId
            ];
        }

        public static function getAuthCode() {
            $response = \Magistraal\Api\call_anonymous('https://argo-web.vercel.app/API/authCode')['body'];

            return $response;
        }

        /* ============================ */
        /*           Absences           */
        /* ============================ */

        public static function absencesList($iso_from, $iso_to) {
            $date_from    = date('Y-m-d', strtotime($iso_from));
            $date_to      = date('Y-m-d', strtotime($iso_to)); 

            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/absenties/?van={$date_from}&tot={$date_to}");

            return $response['body']['Items'] ?? [];
        }

         /* ============================ */
        /*           Courses            */
        /* ============================ */

        public static function courseList() {
            $date_from = '2012-01-01';
            $date_to   = date('Y-m-d', strtotime('+1 year'));

            $courses = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/leerlingen/'.\Magister\Session::$userId."/aanmeldingen/?begin={$date_from}&tot={$date_to}"
            )['body']['items'] ?? null;

            return $courses;
        }

        public static function courseActiveId() {
            $courses = \Magistraal\Courses\get_all(null, null, false);

            foreach ($courses as $course) {
                if($course['active'] !== true) {
                    continue;
                }

                return $course['id'];
            }

            return null;
        }

        /* ============================ */
        /*         Appointments         */
        /* ============================ */

        public static function appointmentList($iso_from, $iso_to) {
            $date_from    = date('Y-m-d', strtotime($iso_from));
            $date_to      = date('Y-m-d', strtotime($iso_to)); 

            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/afspraken/?van={$date_from}&tot={$date_to}");
            
            return $response['body']['Items'] ?? [];
        }

        public static function appointmentFinish($id, $finished = true) {
            $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/afspraken/{$id}", [
                'payload' => [
                    'Id'       => $id,
                    'Afgerond' => $finished
                ],
                'method'  => 'put'
            ]);

            if(isset($response['body']['Uri']) && strpos($response['body']['Uri'], 'afspraken') !== false) {
                return true;
            }
        }

        public static function appointmentGet($id) {
            return \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/afspraken/{$id}")['body'];
        }

        public static function appointmentCreate($appointment) {
            if(!isset($appointment['Id']) || $appointment['Id'] == 0) {
                // Nieuwe afspraak maken
                $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId.'/afspraken', [
                    'payload'   => $appointment,
                    'redirects' => false
                ]);
            } else {
                // Bestaande afspraak bewerken
                $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/afspraken/{$appointment['Id']}", [
                    'payload'   => $appointment,
                    'method'    => 'put',
                    'redirects' => false
                ]);
            }
            return ['success' => $response['info']['success'], 'data' => $response['body']];
        }

        public static function appointmentDelete($id) {
            $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/afspraken/{$id}", [
                'method'    => 'delete',
                'redirects' => false
            ]);
            return ['success' => $response['info']['success'], 'data' => $response['body']];
        }
        
        /* ============================ */
        /*            Files             */
        /* ============================ */

        public static function fileGetLocation($location) {
            $location = \Magister\Session::$domain.$location.'?redirect_type=body';
            $res = \Magistraal\Browser\Browser::request($location, [
                'redirects_max' => 0
            ]);
            
            return $res['body']['location'] ?? null;
        }

        /* ============================ */
        /*            Grades            */
        /* ============================ */

        public static function gradeList($top = 1000) {
            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/cijfers/laatste?top={$top}&skip=0");
            
            return $response['body']['items'] ?? [];
        }

        public static function gradeOverview($course_id = null) {
            $course_id = $course_id ?? \Magister\Session::courseActiveId();

            $grades = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/aanmeldingen/{$course_id}/cijfers/cijferoverzichtvooraanmelding?actievePerioden=false&alleenBerekendeKolommen=false&alleenPTAKolommen=false"
            )['body']['Items'] ?? null;

            return $grades;
        }

        /* ============================ */
        /*      Learning resources      */
        /* ============================ */

        public static function learningresourceList() {
            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId.'/lesmateriaal');

            return $response['body']['Items'] ?? [];
        }

        public static function learningresourceGet($id) {
            $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/digitaallesmateriaal/Ean/{$id}?redirect_type=body", [
                'redirects' => false
            ]);

            return [
                'location' => $response['info']['url'] ?? null
            ];
        }

        /* ============================ */
        /*           Messages           */
        /* ============================ */

        public static function messageList($top = 1000, $skip = 0, $folder = 'inbox') {    
            $magister_folder = ['inbox' => 'postvakin', 'sent' => 'verzondenitems', 'bin' => 'verwijderdeitems'][$folder] ?? $folder;

            $res = \Magistraal\Api\call(\Magister\Session::$domain."/api/berichten/{$magister_folder}/berichten?top={$top}&skip={$skip}");
            $messages = $res['body']['items'] ?? [];

            return $messages;
        }

        public static function messageMarkRead($id, $read = true) {
            $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain."/api/berichten/berichten/", [
                'method' => 'patch',
                'payload' => [
                    'berichten' => [
                        [
                            'berichtId' => $id,
                            'operations' => [
                                [
                                    'op' => 'replace',
                                    'path' => '/IsGelezen',
                                    'value' => $read
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            return $response['info']['success'];
        }

        public static function messageGet($id) {
            $message = \Magistraal\Api\call(
                \Magister\Session::$domain."/api/berichten/berichten/{$id}/"
            )['body'] ?? null;

            if(isset($message['heeftBijlagen']) && $message['heeftBijlagen'] == true) {
                // Voeg bijlage toe aan sidebar
                $location            = $message['links']['bijlagen']['href'] ?? null;
                if(isset($location)) {
                    $message['bijlagen'] = \Magistraal\Api\call(
                        \Magister\Session::$domain.$location
                    )['body']['items'] ?? null;
                } else {
                    $message['bijlagen'] = [];
                }
            }

            return $message;
        }

        public static function messageSend($to = [], $cc = [], $bcc = [], $subject = '', $content = '', $priority = false) {          
            $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain."/api/berichten/berichten/", [
                'method' => 'post',
                'payload' => [
                    'bijlagen' => [],
                    'blindeKopieOntvangers' => $bcc,
                    'heeftPrioriteit' => $priority ?? false,
                    'inhoud' => $content,
                    'kopieOntvangers' => $cc,
                    'onderwerp' => $subject,
                    'ontvangers' => $to,
                    'verzendOptie' => 'standaard'
                ],
                'redirects' => false
            ]);

            return $response['info']['success'];
        }

        public static function messageDelete($id) {
            $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain."/api/berichten/berichten/", [
                'method' => 'patch',
                'payload' => [
                    'berichten' => [
                        [
                            'berichtId' => $id,
                            'operations' => [
                                [
                                    'op' => 'replace',
                                    'path' => '/MapId',
                                    'value' => 3
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            return $response['info']['success'];
        }

        /* ============================ */
        /*            People            */
        /* ============================ */

        public static function peopleList($query) {
            $query  = urlencode($query);
            $response = \Magistraal\Api\call(\Magister\Session::$domain."/api/contacten/personen/?q={$query}&top=250&type=alle");

            if(!$response['info']['success']) {
                return [];
            }

            return $response['body']['items'] ?? [];
        }

        /* ============================ */
        /*            Sources           */
        /* ============================ */

        public static function sourceList($parent_id = -1) {
            $sources = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/bronnen?parentId={$parent_id}&nocache=".time())['body']['Items'] ?? \Magistraal\Response\error('error_getting_sources');
            return $sources;
        }

        /* ============================ */
        /*            Account           */
        /* ============================ */

        public static function accountList() {
            // Laad naam
            $personal = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/account/'
            )['body'] ?? null;

            // Laad email en telefoonnummer
            $contact = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId.'/profiel/'
            )['body'] ?? null;

            // Laad adressen
            $residences = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId.'/adressen/'
            )['body']['items'] ?? null;

            return ['personal' => $personal, 'contact' => $contact, 'residences' => $residences];
        }

        /* ============================ */
        /*          Study guides        */
        /* ============================ */

        public static function studyguideList() {
            $studyguides = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/leerlingen/'.\Magister\Session::$userId.'/studiewijzers'
            )['body']['Items'] ?? null;

            return $studyguides;
        }

        public static function studyguideSourceList($id, $detail_id) {
            $sources = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/leerlingen/'.\Magister\Session::$userId."/studiewijzers/{$id}/onderdelen/{$detail_id}?gebruikMappenStructuur=true"
            )['body']['Bronnen'] ?? null;

            return $sources;
        }

        public static function studyguideGet($id) {
            $studyguide = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/leerlingen/'.\Magister\Session::$userId.'/studiewijzers/'.$id
            )['body'] ?? null;

            return $studyguide;
        }

        /* ============================ */
        /*            Subjects          */
        /* ============================ */
        
        public static function subjectList($course_id = null) {
            $course_id = $course_id ?? \Magister\Session::courseActiveId();

            $subjects = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/aanmeldingen/{$course_id}/vakken"
            )['body'] ?? null;

            return $subjects;
        }

        /* ============================ */
        /*              Tasks           */
        /* ============================ */

        public static function taskList($date_from, $date_to, $top = 1000, $skip = 0) {
            $date_from = $date_from ?? (intval(date('Y'))-1).'-08-01';
            $date_to   = $date_to ?? intval(date('Y')).'-07-31';
            
            $tasks = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/opdrachten?skip={$skip}&top={$top}&startdatum={$date_from}&einddatum={$date_to}&status=alle"
            )['body']['Items'] ?? null;
            
            return $tasks;
        }

        /* ============================ */
        /*            Terms          */
        /* ============================ */
        
        public static function termList($course_id = null) {
            $course_id = $course_id ?? \Magister\Session::courseActiveId();

            $terms = \Magistraal\Api\call(
                \Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/aanmeldingen/{$course_id}/cijfers/cijferperiodenvooraanmelding"
            )['body']['Items'] ?? null;

            return $terms;
        }
    }
?>