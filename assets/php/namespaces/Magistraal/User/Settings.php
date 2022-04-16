<?php 
    namespace Magistraal\User\Settings;

    function get_all($user_uuid = null) {
        if(isset($user_uuid)) {
            $db = \Magistraal\Database\connect();

            $response = $db->q("SELECT * FROM magistraal_userdata WHERE user_uuid='{$user_uuid}'");
            
            $settings = @json_decode($response[0]['settings'] ?? '[]', true) ?? [];
        } else {
            $settings = [];
        }

        $settings_default = @json_decode(@file_get_contents(ROOT.'/config/settings.json'), true)['default'] ?? [];

        return array_replace($settings_default, $settings);
    }

    function get($user_uuid = null, $setting = null) {
        if(!isset($user_uuid) || !isset($setting)) {
            return null;
        }

        $settings = \Magistraal\User\Settings\get_all($user_uuid);

        if(!isset($settings[$setting])) {
            return null;
        }

        return $settings[$setting];
    }

    function set($user_uuid = null, $setting = null, $value = null) {
        if(!isset($user_uuid) || !isset($setting) || !isset($value)) {
            return false;
        }

        $db = \Magistraal\Database\connect();

        $settings = \Magistraal\User\Settings\get_all($user_uuid);
        $settings[$setting] = $value;
        $settings_encoded = json_encode($settings);

        $response = $db->q("UPDATE magistraal_userdata 
                            SET settings='{$settings_encoded}'
                            WHERE user_uuid='{$user_uuid}'");

        return ($response > 0);
    }

    function call($url, $payload = []) {
        return \Magistraal\Browser\Browser::request($url, [
            'payload' => $payload
        ]);
    }
?>