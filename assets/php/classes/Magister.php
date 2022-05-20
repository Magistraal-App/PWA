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

        public static function coursesList() {
            $date_from = '2012-01-01';
            $date_to   = date('Y-m-d', strtotime('+2 years'));

            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/leerlingen/'.\Magister\Session::$userId."/aanmeldingen/?begin={$date_from}&tot={$date_to}");

            return $response['body']['items'] ?? [];
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
        /*           Courses            */
        /* ============================ */

        public static function courseList() {
            $result = [];

            $from_date = '2012-01-01';
            $end_date  = date('Y-m-d', strtotime('+1 year'));

            $courses = \Magistraal\Api\call(\Magister\Session::$domain.'/api/leerlingen/'.\Magister\Session::$userId."/aanmeldingen/?begin={$from_date}&einde={$end_date}")['body']['items'] ?? \Magistraal\Response\error('error_obtaining_courses');

            foreach ($courses as $course) {
                $result[$course['id']] = $course;

                // Load terms for this course
                $terms = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/aanmeldingen/{$course['id']}/cijfers/cijferperiodenvooraanmelding")['body']['Items'] ?? \Magistraal\Response\error('error_getting_terms');
                $result[$course['id']]['terms'] = $terms;

                // Load subjects for this course
                $subjects = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/aanmeldingen/{$course['id']}/vakken")['body'] ?? \Magistraal\Response\error('error_getting_subjects');
                $result[$course['id']]['subjects'] = $subjects;

                // Load grades for this course
                $grades = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/aanmeldingen/{$course['id']}/cijfers/cijferoverzichtvooraanmelding?actievePerioden=false&alleenBerekendeKolommen=false&alleenPTAKolommen=false")['body']['Items'] ?? \Magistraal\Response\error('error_getting_grades');
                $result[$course['id']]['grades'] = $grades;
            }

            return $result;
        }
        
        /* ============================ */
        /*            Files             */
        /* ============================ */

        public static function fileGetLocation($location) {
            $location = \Magister\Session::$domain.$location.'?redirect_type=body';
            $location = \Magistraal\Browser\Browser::request($location)['body']['location'] ?? null;
            
            return $location;
        }

        /* ============================ */
        /*            Grades            */
        /* ============================ */

        public static function gradeList($top = 1000) {
            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/cijfers/laatste?top={$top}&skip=0");
            
            return $response['body']['items'] ?? [];
        }

        /* ============================ */
        /*           Messages           */
        /* ============================ */

        public static function messageList($top = 1000) {
            $res = \Magistraal\Api\call(\Magister\Session::$domain.'/api/berichten/mappen/alle/');
            $folders  = $res['body']['items'] ?? [];
           
            $res = \Magistraal\Api\call(\Magister\Session::$domain."{$folders[0]['links']['berichten']['href']}?top={$top}");
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
            $message = \Magistraal\Browser\Browser::request(\Magister\Session::$domain."/api/berichten/berichten/{$id}/")['body'];

            if(isset($message['heeftBijlagen']) && $message['heeftBijlagen'] == true) {
                // Voeg bijlage toe aan sidebar
                $location            = $message['links']['bijlagen']['href'] ?? null;
                if(isset($location)) {
                    $message['bijlagen'] = \Magistraal\Browser\Browser::request(\Magister\Session::$domain.$location)['body']['items'];
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

        // public function accountList() {
        //     $this->requireAuth('bearer', 'user_info');

        //     $this->obtainEnrollments();

        //     // Load profile (phone number and email address)
        //     $profile = $this->obtainUrlData(
        //         "https://{$this->tenant_domain}/api/personen/{$this->user['id']}/profiel/"
        //     );

        //     // Load addresses
        //     $addresses = $this->obtainUrlData(
        //         "https://{$this->tenant_domain}/api/personen/{$this->user['id']}/adressen/"
        //     );

        //     // Load profile picture
        //     $profile_picture = $this->visitUrl(
        //         "https://{$this->tenant_domain}/api/leerlingen/{$this->user['id']}/foto?redirect_type=body"
        //     )['body'];

        //     return $this->accountListFormat($this->user, $profile, $addresses['items'], $profile_picture);
        // }

        // private function accountListFormat($user, $profile, $addresses, $profile_picture) {
        //     $account = [];

        //     $account = [
        //         'phone'           => $profile['Mobiel'],
        //         'emailaddress'    => $profile['EmailAdres'],
        //         'profile_picture' => base64_encode($profile_picture),
        //         'addresses'       => []
        //     ];

        //     foreach ($addresses as $address) {
        //         if($address['isGeheim'] || $address['type'] != 'Woon') {
        //             continue;
        //         }

        //         $account['addresses'][] = [
        //             'street'       => $address['straat'],
        //             'house_number' => $address['huisnummer'],
        //             'addition'     => $address['toevoeging'] ? $address['toevoeging'] : '',
        //             'zip'          => $address['postcode'],
        //             'place'        => $address['plaats'],
        //             'country'      => $address['land']
        //         ];
        //     }

        //     $account = array_merge($account, $user);

        //     if(is_null($account['infix'])) {
        //         $account['infix'] = '';
        //     }

        //     if(is_null($account['official_infix'])) {
        //         $account['official_infix'] = '';
        //     }

        //     return $account;
        // }

        // public function gradesOverview() {
        //     $this->requireAuth('bearer', 'user_info');

        //     $this->obtainEnrollments();

        //     $all_grades = $all_subjects = $all_terms = [];

        //     foreach ($this->enrollments as $enrollment) {
        //         // Load terms
        //         $terms_request_url = "https://{$this->tenant_domain}/api/personen/{$this->user['id']}/aanmeldingen/{$enrollment['id']}/cijfers/cijferperiodenvooraanmelding";

        //         $terms_request_body = $this->visitUrl($terms_request_url)['body'];

        //         if(!($terms = @json_decode($terms_request_body, true))) {
        //             \Magistraal\Response\error('Failed to load terms.', 'terms_loading_failed');
        //         }

        //         if(!isset($terms['Items'])) {
        //             \Magistraal\Response\error('Failed to load terms.', 'terms_loading_failed');
        //         }

        //         $all_terms[$enrollment['id']] = $terms['Items'];


        //         // Load subjects
        //         $subjects_request_url = "https://{$this->tenant_domain}/api/personen/{$this->user['id']}/aanmeldingen/{$enrollment['id']}/vakken/";

        //         $subjects_request_body = $this->visitUrl($subjects_request_url)['body'];

        //         if(!($subjects = @json_decode($subjects_request_body, true))) {
        //             \Magistraal\Response\error('Failed to load subjects.', 'subjects_loading_failed');
        //         }

        //         $all_subjects[$enrollment['id']] = $subjects;


        //         // Load grades
        //         $grades_request_url = "https://{$this->tenant_domain}/api/personen/{$this->user['id']}/aanmeldingen/{$enrollment['id']}/cijfers/cijferoverzichtvooraanmelding?actievePerioden=false&alleenBerekendeKolommen=false&alleenPTAKolommen=false";

        //         $grades_request_body = $this->visitUrl($grades_request_url)['body'];

        //         if(!($grades = @json_decode($grades_request_body, true))) {
        //             \Magistraal\Response\error('Failed to load grades.', 'grades_loading_failed');
        //         }

        //         if(!isset($grades['Items'])) {
        //             \Magistraal\Response\error('Failed to load grades.', 'grades_loading_failed');
        //         }

        //         if(!empty($grades['Items'])) {
        //             $all_grades[$enrollment['id']] = $grades['Items'];
        //         }
        //     }

        //     return $this->gradesOverviewFormat($all_grades, $all_subjects, $all_terms);
        // }

        // private function gradesOverviewFormat($grades, $subjects, $terms) {
        //     $formatted = [];

        //     foreach ($subjects as $enrollment_id => $subjects_list) {
        //         foreach ($subjects_list as $subject_i => $subject) {
        //             // Change keys
        //             $subjects[$enrollment_id][$subject['id']] = $subjects[$enrollment_id][$subject_i];
        //             unset($subjects[$enrollment_id][$subject_i]);

        //             $formatted[$enrollment_id]['subjects'][] = [
        //                 'code'        => $subject['afkorting'],
        //                 'description' => $subject['omschrijving'],
        //                 'end'         => strtotime($subject['einddatum']),
        //                 'exemption'   => $subject['vrijstelling'] || $subject['heeftOntheffing'] ? true : false,
        //                 'id'          => $subject['id'],
        //                 'seq_number'  => $subject['volgnr'],
        //                 'start'       => strtotime($subject['begindatum']),
        //                 'teacher'     => $subject['docent']
        //             ];
        //         }

        //         usort($formatted[$enrollment_id]['subjects'], function($a, $b) {
        //             return $a['description'] <=> $b['description'];
        //         });
        //     }

        //     foreach ($terms as $enrollment_id => $terms_list) {
        //         foreach ($terms_list as $term_i => $term) {
        //             // Change keys
        //             $terms[$enrollment_id][$term['VolgNummer']] = $terms[$enrollment_id][$term_i];
        //             unset($terms[$enrollment_id][$term_i]);

        //             $formatted[$enrollment_id]['terms'][$term['VolgNummer']] = [
        //                 'description' => $term['Omschrijving'],
        //                 'end'         => strtotime($term['Einde']),
        //                 'name'        => $term['Naam'],
        //                 'seq_number'  => $term['VolgNummer'],
        //                 'start'       => strtotime($term['Start'])
        //             ];
        //         }
        //     }
            
        //     foreach ($grades as $enrollment_id => $gradesList) {
        //         foreach ($gradesList as $grade) {
        //             if(!isset($subjects[$enrollment_id][$grade['Vak']['Id']])) {
        //                 continue;
        //             }

        //             if(!isset($grade['CijferStr'])) {
        //                 continue;
        //             }

        //             if(!isset($formatted[$enrollment_id]['columns'][$grade['CijferKolom']['KolomVolgNummer']])) {
        //                 $formatted[$enrollment_id]['columns'][$grade['CijferKolom']['KolomVolgNummer']] = [
        //                     'description' => $grade['CijferKolom']['KolomOmschrijving'],
        //                     'id'          => $grade['CijferKolom']['Id'],
        //                     'name'        => $grade['CijferKolom']['KolomNaam'],
        //                     'number'      => $grade['CijferKolom']['KolomNummer'],
        //                     'seq_number'  => $grade['CijferKolom']['KolomVolgNummer'],
        //                     'term'        => [
        //                         'name'        => $grade['CijferPeriode']['Naam'],
        //                         'id'          => $grade['CijferPeriode']['Id'],
        //                         'seq_number'  => $grade['CijferPeriode']['VolgNummer']
        //                     ],
        //                     'type'        => $grade['CijferKolom']['KolomSoort'] === 1 ? 'grades' : 'averages'
        //                 ];
        //             }

        //             $formatted[$enrollment_id]['grades'][$grade['CijferId']] = [
        //                 'column'      => [
        //                     'id'          => $grade['CijferKolom']['Id'],
        //                     'number'      => $grade['CijferKolom']['KolomNummer'],
        //                     'seq_number'  => $grade['CijferKolom']['KolomVolgNummer']
        //                 ],
        //                 'entered_at'  => strtotime($grade['DatumIngevoerd']),
        //                 'id'          => $grade['CijferId'],
        //                 'passed'      => $grade['IsVoldoende'],
        //                 'subject'     => [
        //                     'code'        => $grade['Vak']['Afkorting'],
        //                     'description' => $grade['Vak']['Omschrijving'],
        //                     'id'          => $grade['Vak']['Id']
        //                 ],
        //                 'value'       => $this->gradeStrToFloat($grade['CijferStr']),
        //                 'value_str'   => $grade['CijferStr']
        //             ];
        //         }
        //     }

        //     return $formatted;
        // }

        // public static function obtainCourses() {
        //     $from_date = '2012-01-01';
        //     $end_date  = date('Y-m-d', strtotime('+1 year'));

        //     $courses = \Magistraal\Api\call(\Magister\Session::$domain.'/api/leerlingen/'.\Magister\Session::$userId."/aanmeldingen/?begin={$from_date}&einde={$end_date}")['body']['items'] ?? \Magistraal\Response\error('error_obtaining_courses');

        //     $formatted = [];

        //     foreach ($courses as $course) {
        //         $formatted[$course['id']] = [
        //             'id'    => $course['id'],
        //             'start' => [
        //                 'unix' => strtotime($course['begin'])
        //             ],
        //             'end'   => [
        //                 'unix' => strtotime($course['einde'])
        //             ]
        //         ];
        //     }

        //     \Magister\Session::$courses = $formatted;
        // }

        // private function obtainEnrollmentsFormat($enrollments) {
        //     $formatted = [];

        //     foreach ($enrollments as $enrollment) {
        //         $formatted[$enrollment['id']] = [
        //             'id'    => $enrollment['id'],
        //             'start' => strtotime($enrollment['begin']),
        //             'end'   => strtotime($enrollment['einde'])
        //         ];
        //     }

        //     return $formatted;
        // }

        // // public function subjects_list() {
        // //     $this->requireAuth('bearer', 'user_info');

        // //     $this->obtainEnrollments();

        // //     foreach ($this->enrollments as $enrollment) {
        // //         $request_url = "https://{$this->tenant_domain}/api/personen/{$this->user['id']}/aanmeldingen/{$enrollment['id']}/vakken/";

        // //         $request_body = $this->visitUrl($request_url)['body'];

        // //         if(!($subjects = @json_decode($request_body, true))) {
        // //             \Magistraal\Response\error('Failed to load subjects.', 'subjects_loading_failed');
        // //         }
        // //     }

        // //     return $this->subjects_list_format($subjects);
        // // }

        // // private function subjects_list_format($subjects) {
        // //     $formatted = [];

        // //     foreach ($subjects as $subject) {
        // //         $formatted[$subject['id']] = [
        // //             'code'             => $subject['afkorting'],
        // //             'description'      => $subject['omschrijving'],
        // //             'end'              => strtotime($subject['einddatum']),
        // //             'exemption'        => $subject['vrijstelling'],
        // //             'higher_level'     => $subject['hogerNiveau'],
        // //             'id'               => $subject['id'],
        // //             'niveau'           => $subject['niveau'],
        // //             'number'           => $subject['volgnr'],
        // //             'start'            => strtotime($subject['begindatum']),
        // //             'study_id'         => $subject['studieId'],
        // //             'study_subject_id' => $subject['studieVakId'],
        // //             'teacher'          => $subject['docent']
        // //         ];
        // //     }

        // //     return $formatted;
        // // }
    }
?>