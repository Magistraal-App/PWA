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
        public static $tenant;
        public static $tenantSubdomain;
        public static $tokenId;
        public static $userId;
        public static $codeChallenge;
        public static $codeVerifier;

        public static function start() {
            global $_REQUESTHEADERS;
            
            if(!isset($_REQUESTHEADERS['x-auth-token'])) {
                \Magistraal\Response\error('token_invalid');
            }
            
            return \Magister\Session::loginToken($_REQUESTHEADERS['x-auth-token']);
        }

        public static function setupEnvironment() {
            \Magister\Session::$tenantSubdomain = \Magister\Session::$tenant;
            // \Magister\Session::$tenantSubdomain = \Magistraal\Tenants\get(\Magister\Session::$tenant)['subdomain'] ?? \Magistraal\Response\error('tenant_invalid');
            \Magister\Session::$domain          = 'https://'.\Magister\Session::$tenantSubdomain.'.magister.net';
        }

        public static function login($tenant, $username, $password) {
            \Magister\Session::$tenant = strtolower(strtok($tenant, '.'));
            \Magister\Session::setupEnvironment();

            if(!\Magister\Session::loginTenant($tenant)) {
                return ['success' => false, 'info' => 'invalid_tenant'];
            }

            if(!\Magister\Session::loginUsername($username)) {
                return ['success' => false, 'info' => 'invalid_username'];
            }

            if(!\Magister\Session::loginPassword($password)) {
                return ['success' => false, 'info' => 'invalid_password'];
            }
            
            \Magister\Session::obtainTokenData();
            \Magister\Session::obtainUserId();
            
            return ['success' => true, 'token_id' => \Magister\Session::$tokenId ?? null];
        }

        public static function loginToken($token_id) {
            \Magister\Session::obtainTokenData($token_id);
            \Magister\Session::setupEnvironment();
            \Magister\Session::obtainUserId();
        }

        public static function loginTenant($tenant) {
            $login_path = \Magistraal\Authentication\generate_login_path();

            // Redirect to actual login page
            $response  = \Magistraal\Api\call('https://accounts.magister.net'.$login_path);
            $login_url = $response['info']['url'];
            parse_str(parse_url($login_url)['query'], $login_url_query);

            \Magister\Session::$sessionId = $login_url_query['sessionId'] ?? null;
            \Magister\Session::$returnUrl = $login_url_query['returnUrl'] ?? null;
            \Magister\Session::$authCode  = \Magister\Session::getAuthCode();

            // Enter tenant
            $response = \Magistraal\Api\call('https://accounts.magister.net/challenges/tenant/', [
                'authCode'  => \Magister\Session::$authCode,
                'returnUrl' => \Magister\Session::$returnUrl,
                'sessionId' => \Magister\Session::$sessionId,
                'tenant'    => 'f291100d88c24594b984341f002d7471'
            ]);

            return strpos($response['info']['url'], '/error') === false;
        }

        public static function loginUsername($username) {
            $response = \Magistraal\Api\call('https://accounts.magister.net/challenges/username/', [
                'authCode'  => \Magister\Session::$authCode,
                'returnUrl' => \Magister\Session::$returnUrl,
                'sessionId' => \Magister\Session::$sessionId,
                'username'  => $username
            ]);

            return $response['headers']['http_code'] == 200;
        }

        public static function loginPassword($password) {
            $response = \Magistraal\Api\call('https://accounts.magister.net/challenges/password/', [
                'authCode'  => \Magister\Session::$authCode,
                'returnUrl' => \Magister\Session::$returnUrl,
                'sessionId' => \Magister\Session::$sessionId,
                'password'  => $password
            ]);

            return $response['headers']['http_code'] == 200;
        }

        public static function obtainTokenData($token_id = null) {
            if(isset($token_id)) {
                $token_data = \Magistraal\Authentication\token_get($token_id) ?? \Magistraal\Response\error('token_invalid');

                // Token changes when the token expires so it should be updated
                $token_id = $token_data['token_id'];

                // Access token is about to expire
                if(isset($token_data['access_token_expires']) && ($token_data['access_token_expires'] - 30) <= time()) {
                    $bearer = \Magister\Session::getBearer($token_data['refresh_token']);

                    $token_id = \Magistraal\Authentication\token_put([
                        'tenant'               => $token_data['tenant'],
                        'access_token'         => $bearer['access_token'],
                        'access_token_expires' => $bearer['access_token_expires'],
                        'refresh_token'        => $bearer['refresh_token']
                    ], $token_id);
                } else {
                    // Access token is not expiring soon, use it
                    $bearer = $token_data;
                }
            } else {
                // Token does NOT exist or is invalid, create new token
                // echo('CREATING NEW');
                $bearer = \Magister\Session::getBearer();

                $token_id = \Magistraal\Authentication\token_put([
                    'tenant'               => \Magister\Session::$tenant,
                    'access_token'         => $bearer['access_token'],
                    'access_token_expires' => $bearer['access_token_expires'],
                    'refresh_token'        => $bearer['refresh_token']
                ]);
            }
            // var_dump($token_data);
            // var_dump($token_id);

            \Magister\Session::$tokenId            = $token_id ?? \Magister\Session::$tokenId;
            \Magister\Session::$accessToken        = $bearer['access_token'] ?? \Magistraal\Response\error('failed_to_obtain_access_token');
            \Magister\Session::$accessToken        = $bearer['access_token'] ?? \Magistraal\Response\error('failed_to_obtain_access_token');
            \Magister\Session::$refreshToken       = $bearer['refresh_token'] ?? \Magistraal\Response\error('failed_to_obtain_refresh_token');
            \Magister\Session::$tenant             = $token_data['tenant'] ?? \Magister\Session::$tenant;

            // var_dump(\Magister\Session::$tokenId, \Magister\Session::$tenant, \Magister\Session::$refreshToken);
        }

        public static function getBearer($refresh_token = null) {
            if(!isset($refresh_token)) {
                // echo('OBTAINING!');
                if(!isset(\Magister\Session::$returnUrl)) {
                    \Magistraal\Response\error('token_invalid');
                }

                // var_dump(\Magister\Session::$returnUrl);
                
                // Refresh token is not set, get both refresh token and access token 
                $redirect_uri = \Magistraal\Api\call('https://accounts.magister.net'.\Magister\Session::$returnUrl)['info']['url'] ?? null;

                parse_str(parse_url($redirect_uri, PHP_URL_FRAGMENT), $openid);

                if(!isset($openid['code'])) {
                    \Magistraal\Response\error('error_getting_openid_code');
                }
                                
                $bearer = \Magistraal\Browser\Browser::request('https://accounts.magister.net/connect/token', [
                    'headers' => [
                        'x-api-client-id' => 'EF15',
                        'content-type'    => 'application/x-www-form-urlencoded',
                        'host'            => 'accounts.magister.net'
                    ],
                    'payload' => [
                        'client_id' => 'M6LOAPP',
                        'grant_type' => 'authorization_code',
                        'redirect_uri' => 'm6loapp://oauth2redirect/',
                        'code' => $openid['code'],
                        'code_verifier' => \Magister\Session::$codeVerifier
                    ]
                ])['body'] ?? null; 
            } else {
                // echo('REFRESHING!');
                // Refresh token is set, update both refresh token and access token
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
            }

            if(isset($bearer) && is_array($bearer) && isset($bearer['access_token']) && isset($bearer['refresh_token'])) {
                $bearer['access_token_expires'] = time() + $bearer['expires_in'];

                // echo('RETURNING BEARER');
                // var_dump($bearer);

                return $bearer;
            }

            \Magistraal\Response\error('error_getting_bearer');
        }

        // public static function refreshTokens($refresh_token = null) {
        //     $refresh_token = $refresh_token ?? \Magister\Session::$refreshToken ?? null;
        //     // if(!isset($refresh_token)) {
        //     //     \Magistraal\Response\error('refresh_token_not_set');
        //     // }

        //     // // $response = \Magistraal\Browser\Browser::request('https://accounts.magister.net/connect/token', [
        //     // //      'headers' => [
        //     // //         'x-api-client-id' => 'EF15',
        //     // //         'content-type'    => 'application/x-www-form-urlencoded',
        //     // //         'host'            => 'accounts.magister.net'
        //     // //     ],
        //     // //     'payload' => [
        //     // //         'refresh_token' => $refresh_token,
        //     // //         'client_id'     => 'M6LOAPP',
        //     // //         'grant_type'    => 'refresh_token'
        //     // //     ]
        //     // // ]);

        //     // \Magister\Session::$accessToken        = $response['body']['access_token'] ?? null;
        //     // \Magister\Session::$refreshToken       = $response['body']['refresh_token'] ?? null;
        //     // \Magister\Session::$accessTokenExpires = time() + ($response['body']['expires_in'] ?? 0);

        //     // return [
        //     //     'tenant'               => \Magister\Session::$tenant,
        //     //     'access_token'         => \Magister\Session::$accessToken,
        //     //     'access_token_expires' => \Magister\Session::$accessTokenExpires,
        //     //     'refresh_token'        => \Magister\Session::$refreshToken
        //     // ];
        // }

        public static function obtainUserId() {
            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/account?noCache=0');
            
            \Magister\Session::$userId = $response['body']['Persoon']['Id'] ?? \Magistraal\Response\error('failed_to_obtain_user_id');

            return \Magister\Session::$userId;
        }

        public static function getAuthCode() {
            $response = \Magistraal\Api\call_anonymous('https://argo-web.vercel.app/API/authCode')['body'];

            return $response;
        }

        /* ============================ */
        /*           Absences           */
        /* ============================ */

        public static function absencesList($from, $to) {
            $from_date    = date('Y-m-d', $from);
            $to_date      = date('Y-m-d', $to); 

            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/absenties/?van={$from_date}&tot={$to_date}");

            return $response['body']['Items'] ?? [];
        }

         /* ============================ */
        /*           Courses            */
        /* ============================ */

        public static function coursesList() {
            $from_date = '2012-01-01';
            $to_date   = date('Y-m-d', strtotime('+2 years'));

            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/leerlingen/'.\Magister\Session::$userId."/aanmeldingen/?begin={$from_date}&tot={$to_date}");

            return $response['body']['items'] ?? [];
        }

        /* ============================ */
        /*         Appointments         */
        /* ============================ */

        public static function appointmentList($from, $to) {
            $from_date    = date('Y-m-d', $from);
            $to_date      = date('Y-m-d', $to); 

            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId."/afspraken/?van={$from_date}&tot={$to_date}");

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
            $response = \Magistraal\Browser\Browser::request(\Magister\Session::$domain.'/api/personen/'.\Magister\Session::$userId.'/afspraken', [
                'payload'   => $appointment,
                'redirects' => false
            ]);
            return ['success' => ($response['headers']['http_code'] == 201), 'data' => $response['body']];
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
            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/berichten/mappen/alle/');
            $folders  = $response['body']['items'];
           
            $response = \Magistraal\Api\call(\Magister\Session::$domain."{$folders[0]['links']['berichten']['href']}?top={$top}");
            $messages = $response['body']['items'];

            return $messages;
        }

        public static function messageMarkRead($id, $read = true) {
            return \Magistraal\Browser\Browser::request(\Magister\Session::$domain."/api/berichten/berichten/", [
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

        /* ============================ */
        /*            People            */
        /* ============================ */

        public static function peopleList($query) {
            $query  = urlencode($query);
            $response = \Magistraal\Api\call(\Magister\Session::$domain."/api/contacten/personen/?q={$query}&top=250&type=alle");

            if($response['info']['http_code'] != 200) {
                return [];
            }

            return $response['body']['items'];
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

        // private function obtainEnrollments() {
        //     $this->requireAuth('bearer', 'user_info');

        //     $request_url = "https://{$this->tenant_domain}/api/leerlingen/{$this->user['id']}/aanmeldingen/";

        //     $request_body = $this->visitUrl($request_url)['body'];

        //     if(!($enrollments = @json_decode($request_body, true))) {
        //         \Magistraal\Response\error('Failed to load enrollments.', 'enrollments_loading_failed');
        //     }

        //     if(!isset($enrollments['items'])) {
        //         \Magistraal\Response\error('Failed to load enrollments.', 'enrollments_loading_failed');
        //     }

        //     $this->enrollments = $this->obtainEnrollmentsFormat($enrollments['items']);
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