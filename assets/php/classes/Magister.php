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
                \Magistraal\Response\error('header_x-auth-token_missing', 200);
            }
            
            return \Magister\Session::loginToken($_REQUESTHEADERS['x-auth-token']);
        }

        public static function setupEnvironment() {
            \Magister\Session::$tenantSubdomain = \Magister\Session::$tenant;
            // \Magister\Session::$tenantSubdomain = \Magistraal\Tenants\get(\Magister\Session::$tenant)['subdomain'] ?? \Magistraal\Response\error('tenant_invalid');
            \Magister\Session::$domain          = 'https://'.\Magister\Session::$tenantSubdomain.'.'.\Magistraal\Config\get('domain');
        }

        public static function login($tenant, $username, $password) {
            \Magister\Session::$tenant = $tenant;
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
            
            \Magister\Session::obtainTokens();
            \Magister\Session::storeUserId();
            
            return ['success' => true, 'token_id' => \Magistraal\Authentication\token_put([
                'tenant'               => $tenant,
                'access_token'         => \Magister\Session::$accessToken,
                'access_token_expires' => \Magister\Session::$accessTokenExpires,
                'refresh_token'        => \Magister\Session::$refreshToken
            ])];
        }

        public static function loginToken($token_id) {
            $token_data = \Magistraal\Authentication\token_get($token_id);
            if($token_data === false) {
                \Magistraal\Response\error('token_invalid');
            }

            \Magister\Session::$tokenId = $token_id;

            // Use access token if it's not about to expire
            if(($token_data['access_token_expires'] - time()) > 60) {
                \Magister\Session::$tenant             = $token_data['tenant'];
                \Magister\Session::$accessToken        = $token_data['access_token'];
                \Magister\Session::$accessTokenExpires = $token_data['access_token_expires'];
                \Magister\Session::setupEnvironment();
                \Magister\Session::storeUserId();
            } else {    
                // Create new access token using refresh token
                // $tenant = $token_data['tenant'];
                // $username = \Magistraal\Encryption\decrypt($token_data['username'], 2);
                // $password = \Magistraal\Encryption\decrypt($token_data['password'], 1);
                // \Magister\Session::login($tenant, $username, $password);
            }
        }

        // private function obtainAuthCode() {
        //     $request_url = "https://{$this->config('domain_accounts')}/challenges/current/";

        //     try {
        //         $request_body = json_decode($this->visitUrl($request_url)['body']);

        //         if(isset($request_body['authCode'])) {
        //             return $request_body['authCode'];
        //         }

        //         return false;
        //     } catch(\Exception $e) {
        //         \Magistraal\Response\error('Failed to grab auth_code.');
        //     }

        //     return false;
        // }

        public static function loginTenant($tenant) {
            $login_path = \Magistraal\Authentication\generate_login_path();

            // Redirect to actual login page
            $response  = \Magistraal\Api\call(\Magistraal\Config\get('domain_accounts').$login_path);
            $login_url = $response['info']['url'];
            parse_str(parse_url($login_url)['query'], $login_url_query);

            \Magister\Session::$sessionId = $login_url_query['sessionId'] ?? null;
            \Magister\Session::$returnUrl = $login_url_query['returnUrl'] ?? null;
            \Magister\Session::$authCode  = \Magister\Session::getAuthCode();

            // Enter tenant
            $response = \Magistraal\Api\call(\Magistraal\Config\get('domain_accounts').'/challenges/tenant/', [
                'authCode'  => \Magister\Session::$authCode,
                'returnUrl' => \Magister\Session::$returnUrl,
                'sessionId' => \Magister\Session::$sessionId,
                'tenant'    => 'f291100d88c24594b984341f002d7471'
            ]);

            return strpos($response['info']['url'], '/error') === false;
        }

        public static function loginUsername($username) {
            $response = \Magistraal\Api\call(\Magistraal\Config\get('domain_accounts').'/challenges/username/', [
                'authCode'  => \Magister\Session::$authCode,
                'returnUrl' => \Magister\Session::$returnUrl,
                'sessionId' => \Magister\Session::$sessionId,
                'username'  => $username
            ]);

            return $response['headers']['http_code'] == 200;
        }

        public static function loginPassword($password) {
            $response = \Magistraal\Api\call(\Magistraal\Config\get('domain_accounts').'/challenges/password/', [
                'authCode'  => \Magister\Session::$authCode,
                'returnUrl' => \Magister\Session::$returnUrl,
                'sessionId' => \Magister\Session::$sessionId,
                'password'  => $password
            ]);

            return $response['headers']['http_code'] == 200;
        }

        public static function obtainTokens() {
            // $response = \Magistraal\Browser\Browser::request(\Magistraal\Config\get('domain_accounts').'/connect/token', [
            //     'headers' => [
            //         'X-API-Client-ID' => 'EF15',
            //         'Content-Type'    => 'application/x-www-form-urlencoded'
            //     ],
            //     'payload' => [
            //         'code' => \Magister\Session::$codeChallenge,
            //         'code_verifier' => \Magister\Session::$codeVerifier,
            //         'client_id' => 'M6LOAPP',
            //         'grant_type' => 'authorization_code',
            //         'redirect_uri' => 'm6loapp%3A%2F%2Foauth2redirect%2F'
            //     ]
            // ]);
            $response     = \Magistraal\Api\call(\Magistraal\Config\get('domain_accounts').\Magister\Session::$returnUrl);
            $redirect_uri = $response['info']['url'];

            parse_str(parse_url($redirect_uri, PHP_URL_FRAGMENT), $openid);
            
            print_r('Code verifier: '.\Magister\Session::$codeVerifier."\n");
            print_r('Code challenge: '.\Magister\Session::$codeChallenge."\n");
            print_r('Code: '.$openid['code']."\n\n\n");
            $response = \Magistraal\Browser\Browser::request(\Magistraal\Config\get('domain_accounts').'/connect/token', [
                'headers' => [
                    'X-API-Client-ID' => 'EF15',
                    'Content-Type'    => 'application/x-www-form-urlencoded',
                    'Host'            => 'accounts.magister.net'
                ],
                'payload' => [
                    'client_id' => 'M6LOAPP',
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => 'm6loapp://oauth2redirect/',
                    'code' => '2204081m6NTDIjGsoPPRNZ9m4-NjggHpsKgo6znAM', //$openid['code'],
                    'code_verifier' => 'r-kszNPcaAIh0Q8PlZgSLE4Z7bSScx1IgATBTn2-dmE' // \Magister\Session::$codeVerifier
                ]
            ]);


            var_dump($response);

            // if(empty($bearer)) {
            //     \Magistraal\Response\error('error_obtaining_bearer');
            // }

            \Magister\Session::$accessToken        = $bearer['access_token'] ?? null;
            \Magister\Session::$refreshToken       = $bearer['refresh_token'] ?? null;
            \Magister\Session::$accessTokenExpires = time() + ($bearer['expires_in'] ?? 0);

            return true;
        }

        public static function refreshAccessToken() {
            if(!isset(\Magister\Session::$refreshToken)) {
                \Magistraal\Response\error('refresh_token_not_set');
            }

            $response = \Magistraal\Api\call_anonymous('https://accounts.magister.net/connect/token/', [
                'refresh_token' => \Magister\Session::$refreshToken,
                'client_id'     => 'M6LOAPP',
                'grant_type'    => 'refresh_token'
            ]);
        }

        public static function storeUserId() {
            $response = \Magistraal\Api\call(\Magister\Session::$domain.'/api/account?noCache=0');
            \Magister\Session::$userId = $response['body']['Persoon']['Id'] ?? null;

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

            if(strpos($response['body'], 'afspraken') !== false) {
                return true;
            }
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

        // private function appointmentsListFormat($appointments, $from = null, $to = null) {
        //     $formatted_appointments = [];

        //     // Generate an array with keys of dates between from and to
        //     $appointments_unixes = range($from, $to, 86400);
        //     foreach ($appointments_unixes as $unix) {
        //         $formatted_appointments[date('Y-m-d', $unix)] = [
        //             'unix' => $unix,
        //             'appointments' => []
        //         ];
        //     }

        //     $remappings = [
        //         'status' => [
        //             1  => 'lesson',
        //             2  => 'custom',
        //             3  => 'lesson',
        //             4  => 'canceled',
        //             5  => 'lesson',
        //             6  => 'lesson',
        //             7  => 'lesson',
        //             8  => 'lesson',
        //             9  => 'lesson',
        //             10 => 'changed'
        //         ],
        //         'info_type' => [ // Toets
        //             1  => 'homework',           // Huiswerk
        //             2  => 'test',               // Proefwerk
        //             3  => 'exam',               // Tentamen
        //             4  => 'written_examination' // SO
        //         ],
        //         'type' => [ // Appointment type
        //             1  => 'true',  // Appointment created by user
        //             2  => 'false', // Appointment created by admin
        //             13 => 'false'  // Appointment according to timetable
        //         ]
        //     ];

        //     foreach ($appointments['Items'] as $appointment) {
        //         $start               = strtotime($appointment['Start']);
        //         $end                 = strtotime($appointment['Einde']);
        //         $start_date          = date('Y-m-d', $start);
        //         $info_type           = $remappings['info_type'][$appointment['InfoType']] ?? 'none';

        //         // Some appointments passed don't fit in the desired timespan, skip those
        //         if(!isset($formatted_appointments[$start_date])) {
        //             continue;
        //         }

        //         // Seperate Microsoft Teams meeting link from content
        //         list($content_no_ms_teams, $ms_teams_link) = $this->appointmentSeperateContent($appointment['Inhoud']);

        //         // Obtain link only
        //         if(strpos($appointment['Inhoud'], '://teams.microsoft.com/l/meetup-join/') !== false) {
        //             $ms_teams_link = 'https://teams.microsoft.com/l/meetup-join/'.str_between('://teams.microsoft.com/l/meetup-join/', '"', $appointment['Inhoud']);
        //         }

        //         // Calculate content length
        //         $content_length = strlen(trim(strip_tags($content_no_ms_teams)));

        //         // Clear type if homework and no content exists
        //         if($appointment['InfoType'] && $content_length == 0) {
        //             $info_type = 'none';
        //         }

        //         // Clear facility when nothing is set
        //         if(in_array($appointment['Lokatie'], ['""', '\'\'', '-', null, 'null'])) {
        //             $appointment['Lokatie'] = '';
        //         }

        //         $formatted_appointments[$start_date]['appointments'][] = [
        //             'all_day'          => $appointment['DuurtHeleDag'],
        //             'content_length'   => $content_length,
        //             'content_text'     => strip_tags(str_replace('</p>', '</p> ', $content_no_ms_teams)),
        //             'content'          => $content_no_ms_teams,
        //             'designation'      => $appointment['Omschrijving'],
        //             'duration'         => [
        //                 'seconds'           => ($end - $start),
        //                 'lessons'           => ($appointment['LesuurTotMet']+1 ?? 0) - ($appointment['LesuurVan'] ?? 0) 
        //             ],
        //             'end'              => [
        //                 'unix'              => $end,
        //                 'lesson'            => $appointment['LesuurTotMet'] ?? 0
        //             ],  
        //             'facility'         => $appointment['Lokatie'],
        //             'finished'         => $appointment['Afgerond'],
        //             'has_meeting_link' => $ms_teams_link == '' ? false : true,
        //             'id'               => $appointment['Id'],
        //             'info_type'        => $info_type,
        //             'meeting_link'     => $ms_teams_link,
        //             'start'            => [
        //                 'unix'              => $start,
        //                 'lesson'            => $appointment['LesuurVan'] ?? 0
        //             ],  
        //             'status'           => $remappings['status'][$appointment['Status']] ?? $remappings['status'][1],
        //             'subjects'         => array_column($appointment['Vakken'], 'Naam'),
        //             'teachers'         => array_column($appointment['Docenten'], 'Naam'),
        //             'type'             => $info_type
        //         ];
        //     }

        //     return $formatted_appointments;
        // }

        // public function appointmentFinish($id, $finished = true) {
        //     $this->requireAuth('bearer', 'user_info');

        //     $request_url  = "https://{$this->tenant_domain}/api/personen/{$this->user['id']}/afspraken/{$id}";
            
        //     $request_body = $this->visitUrl($request_url, [
        //         'payload' => [
        //             'Id'       => $id,
        //             'Afgerond' => $finished
        //         ],
        //         'method'  => 'put'
        //     ])['body'];

        //     if(strpos($request_body, 'afspraken') !== false) {
        //         return true;
        //     }
            
        //     return false;
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

        // private function gradesListFormat($grades) {
        //     $output = [];

        //     foreach ($grades as $grade) {
        //         $output[] = [
        //             'column_id'   => $grade['kolomId'],
        //             'counts'      => $grade['teltMee'],
        //             'description' => $grade['omschrijving'],
        //             'entered_at'  => strtotime($grade['behaaldOp'] ?? $grade['ingevoerdOp'] ?? null),
        //             'exemption'   => $grade['heeftVrijstelling'],
        //             'got_at'      => strtotime($grade['behaaldOp'] ?? $grade['ingevoerdOp'] ?? null),
        //             'make_up'     => $grade['moetInhalen'],
        //             'passed'      => $grade['isVoldoende'],
        //             'subject'     => [
        //                 'code'        => $grade['vak']['code'],
        //                 'description' => $grade['vak']['omschrijving']
        //             ],
        //             'value_str'   => $grade['waarde'],
        //             'value'       => $this->gradeStrToFloat($grade['waarde']),
        //             'weight'      => $grade['weegfactor']
        //         ];
        //     }

        //     return $output;
        // }

        // public function messageRead($message_id = null, $state = 'read') {
        //     $this->requireAuth('bearer', 'user_info');

        //     $read = ($state == true || $state == 'read' ? true : false);

        //     $this->visitUrl("https://{$this->tenant_domain}/api/berichten/berichten/", [
        //         'method' => 'patch',
        //         'payload' => [
        //         'berichten' => [
        //                 [
        //                     'berichtId' => intval($message_id),
        //                     'operations' => [
        //                         [
        //                             'op' => 'replace',
        //                             'path' => '/IsGelezen',
        //                             'value' => $read
        //                         ]
        //                     ]
        //                 ]
        //             ]
        //         ]
        //     ]);

        //     return true;
        // }

        // public function messageSend($message) {
        //     $this->requireAuth('bearer', 'user_info');

        //     $this->visitUrl("https://{$this->tenant_domain}/api/berichten/berichten/", [
        //         'payload' => [
        //             'heeftPrioriteit'       => $message['priority'] ?? false,
        //             'ontvangers'            => $message['to'] ?? [],
        //             'kopieOntvangers'       => $message['cc'] ?? [],
        //             'blindeKopieOntvangers' => $message['bcc'] ?? [],
        //             'onderwerp'             => $message['subject'],
        //             'inhoud'                => $message['content'] ?? '',
        //             'bijlagen'              => [],
        //             'verzendOptie'          => 'standaard'
        //         ]
        //     ]);
        // }

        // private function messagesListFormat($messages) {
        //     $formatted = [];
            
        //     foreach ($messages as $message) {
        //         $formatted[] = [
        //             'folder_id'       => $message['mapId'],
        //             'forwarded_at'    => strtotime($message['doorgestuurdOp']),
        //             'has_attachments' => $message['heeftBijlagen'],
        //             'id'              => $message['id'],
        //             'priority'        => $message['heeftPrioriteit'] ? 1 : 0,
        //             'read'            => $message['isGelezen'],
        //             'replied_at'      => strtotime($message['beantwoordOp']),
        //             'sender'          => [
        //                 'id'              => $message['afzender']['id'],
        //                 'name'            => $message['afzender']['naam']
        //             ],
        //             'sent_at'         => strtotime($message['verzondenOp']),
        //             'subject'         => $message['onderwerp']
        //         ];
        //     }

        //     return $formatted;
        // }

        // public function messageInfo($message_id) {
        //     $this->requireAuth('bearer', 'user_info');

        //     $request_url  = "https://{$this->tenant_domain}/api/berichten/berichten/{$message_id}/";
        //     $message_info = $this->obtainUrlData($request_url);

        //     return $this->messageInfoFormat($message_info);
        // }

        // private function messageInfoFormat($message) {
        //     $formatted = [
        //         'content'    => $message['inhoud'],
        //         'folder_id'       => $message['mapId'],
        //         'forwarded_at'    => strtotime($message['doorgestuurdOp']),
        //         'has_attachments' => $message['heeftBijlagen'],
        //         'id'              => $message['id'],
        //         'priority'        => $message['heeftPrioriteit'] ? 1 : 0,
        //         'read'            => $message['isGelezen'],
        //         'recipients' => [
        //             'bcc'        => ['names' => [], 'list' => []],
        //             'cc'         => ['names' => [], 'list' => []],
        //             'to'         => ['names' => [], 'list' => []]
        //         ],
        //         'replied_at'      => strtotime($message['beantwoordOp']),
        //         'sender'     => [
        //             'id'         => $message['afzender']['id'],
        //             'name'       => $message['afzender']['naam']
        //         ],
        //         'sent_at'         => strtotime($message['verzondenOp']),
        //         'subject'         => $message['onderwerp']
        //     ];

        //     // Store recipient (bcc) name and id
        //     foreach ($message['blindeKopieOntvangers'] as $recipient) {
        //         $formatted['recipients']['bcc']['list'][] = [
        //             'id'   => $recipient['id'],
        //             'name' => $recipient['weergavenaam']
        //         ];

        //         $formatted['recipients']['bcc']['names'][] = $recipient['weergavenaam'];
        //     }

        //     // Store recipient (cc) name and id
        //     foreach ($message['kopieOntvangers'] as $recipient) {
        //         $formatted['recipients']['cc']['list'][] = [
        //             'id'   => $recipient['id'],
        //             'name' => $recipient['weergavenaam']
        //         ];

        //         $formatted['recipients']['cc']['names'][] = $recipient['weergavenaam'];
        //     }

        //     // Store recipient (to) name and id
        //     foreach ($message['ontvangers'] as $recipient) {
        //         $formatted['recipients']['to']['list'][] = [
        //             'id'   => $recipient['id'],
        //             'name' => $recipient['weergavenaam']
        //         ];

        //         $formatted['recipients']['to']['names'][] = $recipient['weergavenaam'];
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

        // private function storeCookies($cookies) {
        //     if(!is_array($cookies)) {
        //         $cookies = [$cookies];
        //     }

        //     foreach ($cookies as $cookie) {
        //         // Only read until first ;
        //         $cookie = explode('; ', $cookie);

        //         // Split at last occurence of =
        //         list($name, $value) = str_split_pos($cookie[0], strrpos($cookie[0], '='));

        //         // Clear cookie when expired
        //         if(
        //             isset($cookie[1]) &&
        //             substr($cookie[1], 0, 7) == 'expires' && 
        //             strtotime($cookie[1]) < time()
        //         ) {
        //             unset($this->cookies[$name]);
        //             continue;
        //         }

        //         // Remove = from start of value
        //         $value = substr($value, 1);

        //         // Save cookie
        //         $this->cookies[$name] = $value;
        //     }

        //     return true;
        // }

        // private function buildCookieHeader() {
        //     $cookies_header = '';

        //     foreach ($this->cookies as $cookie_key => $cookie_value) {
        //         $cookies_header .= "{$cookie_key}={$cookie_value}; ";
        //     }

        //     return rtrim($cookies_header, ' ');
        // }

        // private function visitUrl($url, $options = []) {
        //     return false;
        //     // Options
        //     $options = array_replace([
        //         'payload'       => [], 
        //         'method'        => 'post',
        //         'store_cookies' => true,
        //         'anonymous'     => false
        //     ], $options);

        //     $user_agent  = $this->config('default_user_agent');

        //     $headers     = [
        //         "Origin: https://{$this->config('domain_accounts')}",
        //         "Referrer: https://{$this->config('domain_accounts')}",
        //         'Sec-Fetch-Dest: empty',
        //         'Sec-Fetch-Mode: cors',
        //         'Sec-Fetch-Site: same-origin',
        //         "User-Agent: {$user_agent}"
        //     ];

        //     if($options['send_cookies'] == true) {
        //         $headers[] = "cookie: {$this->buildCookieHeader()}";
        //     }

        //     if(isset($this->cookies['XSRF-TOKEN']) && $options['send_xsrf'] == true) {
        //         $headers[] = "X-Xsrf-Token: {$this->cookies['XSRF-TOKEN']}";
        //     }

        //     if(isset($this->bearer) && $options['send_bearer'] == true) {
        //         $headers[] = "Authorization: Bearer {$this->bearer}";
        //     }

        //     $headers[] = 'Content-Type: application/json';

        //     $ch = curl_init($url);
        //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //     curl_setopt($ch, CURLOPT_HEADER, true);
        //     curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        //     if($options['method'] == 'post') {
        //         if(!empty($options['payload'])) {
        //             curl_setopt($ch, CURLOPT_POST, true);
        //             curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['payload']));
        //         }
        //     } else {
        //         curl_setopt($ch, CURLOPT_POST, false);
        //         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['payload']));
        //         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($options['method']));
        //     }


        //     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        //     $response    = curl_exec($ch);
        //     $info        = curl_getinfo($ch);
        //     $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        //     $headers     = $this->headersArray(substr($response, 0, strpos($response, "\r\n\r\n")));
        //     $headers_out = $this->headersArray(curl_getinfo($ch, CURLINFO_HEADER_OUT));
        //     curl_close($ch);

        //     // Store cookies
        //     if(isset($headers['set-cookie']) && $options['storeCookies'] == true) {
        //         $this->storeCookies($headers['set-cookie']);
        //     }

        //     // Follow redirect if document has moved
        //     if(
        //         $options['follow_redirects'] == true && 
        //         isset($headers['http_code']) && $headers['http_code'] == '302' && 
        //         isset($headers['location'])
        //     ) {
        //         if(strpos($headers['location'], '/') === 0) {
        //             $headers['location'] = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).$headers['location'];
        //         }

        //         return $this->visitUrl($headers['location'], $options);
        //     }

        //     $body = trim(str_split_pos($response, strpos($response, "\r\n\r\n"))[1]);

        //     // Returned "Invalid API key" / bearer access token
        //     if(stripos($body, 'invalid api') !== false && stripos($body, 'invalid api') < 20) {
        //         if($options['exit_when_invalid_bearer'] === true) {
        //             \Magistraal\Response\error("Invalid bearer access token.", 'error_generic');
        //         }
                    
        //         return false;
        //     }

        //     return [
        //         'body'        => $body,
        //         'headers_out' => $headers_out, 
        //         'headers'     => $headers, 
        //         'info'        => $info,
        //         'response'    => $response
        //     ];
        // }

        // private function obtainUrlData($url) {
        //     $response = $this->visitUrl($url);

        //     if(!isset($response['body'])) {
        //         \Magistraal\Response\error("Failed to load response from url {$url}: body does not exist.", 'url_loading_failed');
        //     }

        //     if(!($data = @json_decode($response['body'], true))) {
        //         \Magistraal\Response\error("Failed to decode response from url {$url}.", 'url_decoding_failed');
        //     }

        //     return $data;
        // }

        // private function headersArray($headers_str) {
        //     $headers = [];

        //     $headers_arr = explode("\r\n", $headers_str);
        //     foreach ($headers_arr as $i => $header_str) {
        //         if($i === 0) {
        //             if(strpos($header_str, 'HTTP') === 0) {
        //                 $headers['http_code'] = trim(str_split_pos($header_str, strpos($header_str, ' '))[1]);
        //             }
        //         }

        //         $colons = strpos_all($header_str, ':');
        //         if(!isset($colons[0])) {
        //             continue;
        //         }

        //         $split_pos = $colons[0] === 0 && isset($colons[1]) ? $colons[1] : $colons[0];

        //         list($header_key, $header_value) = str_split_pos($header_str, $split_pos);

        //         $header_value = substr($header_value, 2); // Remove space and colon

        //         if(isset($headers[$header_key])) {
        //             // Turn into array if header was sent multiple times (i.e. set-cookie)
        //             if(!is_array($headers[$header_key])) {
        //                 $headers[$header_key] = [$headers[$header_key]];
        //             }
        //             array_push($headers[$header_key], $header_value);
        //         } else {
        //             $headers[$header_key] = $header_value;
        //         }
        //     }

        //     return $headers;
        // }
        
        // private function generateRedirectUri() {
        //     return urlencode("https://{$this->tenant_domain}/oidc/redirect_callback.html").'&response_type=id_token%20token&scope=openid%20profile%20opp.read%20opp.manage%20attendance.overview%20calendar.ical.user';
        //     // return 'https%3A%2F%2Foauth2redirect%2F&scope=openid%20profile%20offline_access%20magister.mobile%20magister.ecs&response_type=code%20id_token';
        // }

        // private function generateClientId() {
        //     return "M6-{$this->tenant_domain}";
        //     // return 'M6LOAPP';
        // }

        // private function requireAuth($auth_types) {
        //     $auth_types = func_get_args();

        //     foreach ($auth_types as $auth_type) {
        //         switch ($auth_type) {
        //             case 'bearer':
        //                 if(!isset($this->bearer)) {
        //                     \Magistraal\Response\error('Failed to authenticate user: bearer not set.');
        //                 }
        //                 break;

        //             case 'user_info':
        //                 if(!isset($this->user)) {
        //                     \Magistraal\Response\error('Failed to authenticate user: user_info not set.');
        //                 }
        //                 break;

        //             case 'session_id':
        //                 if(!isset($this->session_id)) {
        //                     \Magistraal\Response\error('Failed to authenticate user: session_id not set.');
        //                 }
        //                 break;

        //             case 'return_url':
        //                 if(!isset($this->return_url)) {
        //                     \Magistraal\Response\error('Failed to authenticate user: return_url not set.');
        //                 }
        //                 break;
                    
        //             default:
        //                 \Magistraal\Response\error('Failed to authenticate user: unknown.');
        //                 break;
        //         }
        //     }
        // }

        // private function generateState($length = 32) {
        //     return random_hex($length);
        // }

        // private function generateNonce($length = 32) {
        //     return random_hex($length);
        // }

        // private function gradeStrToFloat($grade) {
        //     $grade = strtolower(trim(str_replace(',', '.', $grade)));
        //     $textual_mapping = [
        //         'zs' => 1,
        //         's'  => 2,
        //         'ro' => 3,
        //         'o'  => 4,
        //         'm'  => 5,
        //         'v'  => 6,
        //         'rv' => 7,
        //         'g'  => 8,
        //         'zg' => 9,
        //         'u'  => 10
        //     ];

        //     if(is_numeric($grade)) {
        //         return round(floatval($grade), 1);
        //     } else if(isset($textual_mapping[$grade])) {
        //         return $textual_mapping[$grade];
        //     }

        //     return false;
        // }

        // private function appointmentSeperateContent($content) {
        //     $ms_teams_link = '';

        //     if(empty($content)) {
        //         return ['', ''];
        //     }

        //     $content_dom = new \DOMDocument();
        //     $content_dom->encoding = 'utf-8';
        //     $content_dom->loadHTML(utf8_decode($content));

        //     $content_nodes         = [];
        //     $remove_nodes          = [];
        //     $content_nodes['a']    = $content_dom->getElementsByTagName('a');
        //     $content_nodes['span'] = $content_dom->getElementsByTagName('span');
        //     $content_nodes['hr' ]  = $content_dom->getElementsByTagName('hr');
        //     $content_nodes['p' ]   = $content_dom->getElementsByTagName('p');

        //     foreach ($content_nodes as $nodes) {
        //         for ($i=0; $i < count($nodes); $i++) { 
        //             $node = $nodes->item($i);

        //             switch ($node->tagName) {
        //                 case 'a':
        //                     if(!$node->hasAttribute('href')) {
        //                         continue 2;
        //                     }

        //                     $href = $node->getAttribute('href');
        //                     if(stripos($href, '//teams.microsoft.com/l/meetup-join/') !== false) {
        //                         $ms_teams_link = $href;
        //                         $remove_nodes[] = $node;
        //                     } else if(
        //                         stripos($href, '//teams.microsoft.com/meetingOptions/') !== false ||
        //                         stripos($href, '//support.office.com/') !== false
        //                     ) {
        //                         $remove_nodes[] = $node;
        //                     }
        //                     break;
                        
        //                 case 'span':
        //                     if(trim($node->textContent) == '|') {
        //                         $remove_nodes[] = $node;
        //                     }
        //                     break;

        //                 case 'hr':
        //                     $remove_nodes[] = $node;
        //                     break;

        //                 case 'p':
        //                     if(trim($node->textContent) == '|') {
        //                         $remove_nodes[] = $node;
        //                     }
        //                     break;

        //                 default:
        //                     continue 2;
        //             }
        //         }
        //     }

        //     foreach ($remove_nodes as $node) {
        //         $node->parentNode->removeChild($node);
        //     }

        //     $content_html = $content_dom->saveHTML($content_dom->documentElement);

        //     $content_html = preg_replace('!\s+!', ' ', $content_html);

        //     // Remove semi-empty tags and everything outside body
        //     $content_html = str_between(
        //         '<body>', '</body>', str_replace([
        //             '<p><br></p>',
        //             '<p> </p>',
        //             '<p> | </p>',
        //             '<p>|</p>',
        //             '<p></p>'
        //         ], '', $content_html)
        //     );

        //     return [$content_html, $ms_teams_link];
        // }
    }
?>