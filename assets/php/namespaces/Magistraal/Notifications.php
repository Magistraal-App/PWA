<?php 
    namespace Magistraal\Notifications;

    function register_fcm_token($user_uuid, $fcm_messages_token) {
       $fcm_messages_tokens = \Magistraal\Notifications\get_registered_fcm_tokens($user_uuid);

        // Store new FCM token and timestamp
        $fcm_messages_tokens[$fcm_messages_token] = ['updated' => time()];
        
        // Store new FCM tokens
        \Magistraal\Database\query('UPDATE `magistraal_userdata` SET `fcm_messages_tokens`=? WHERE `user_uuid`=?', [json_encode($fcm_messages_tokens), $user_uuid]);
        return true;
    }

    function get_registered_fcm_tokens($user_uuid) {
        // Load FCM tokens from database
        $fcm_messages_tokens = json_decode(\Magistraal\Database\query("SELECT `fcm_messages_tokens` FROM `magistraal_userdata` WHERE `user_uuid`=?", $user_uuid)[0]['fcm_messages_tokens'] ?? null, true) ?? [];
        
        // Transform FCM tokens to array
        if(!is_array($fcm_messages_tokens)) {
            $fcm_messages_tokens = [$fcm_messages_tokens];
        }

        return $fcm_messages_tokens;
    }

    class Notification {
        private $recipientUserUuid;
        public $notification;

        public function __construct(string $recipient_user_uuid) {
            $this->recipientUserUuid = $recipient_user_uuid;
        }

        public function send() {
            // Load Firebase tokens
            $fcm_messages_tokens = \Magistraal\Notifications\get_registered_fcm_tokens($this->recipientUserUuid);
            
            $to = [];
            foreach ($fcm_messages_tokens as $fcm_messages_token => $token_data) {
                $to[] = $fcm_messages_token;
            }
            
            $this->sendFirebase($to, $this->notification);
            
            return $this;
        }

        private function sendFirebase($to, array $notification) {
            $firebase_server_key = \Magistraal\Encryption\decrypt(\Magistraal\Config\get('firebase_server_key'));

            if(!isset($firebase_server_key)) {
                return false;
            }

            $res = \Magistraal\Browser\Browser::request('https://fcm.googleapis.com/fcm/send', [
                'headers'   => [
                    'authorization' => 'key='.$firebase_server_key
                ],
                'payload' => [
                    'registration_ids' => is_array($to) ? $to : [$to],
                    'notification' => $notification
                ],
                'anonymous' => true
            ]);
        }
    }
?>