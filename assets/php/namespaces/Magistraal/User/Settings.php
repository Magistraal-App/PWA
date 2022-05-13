<?php 
    namespace Magistraal\User\Settings;

    function get_all($user_uuid = null) {
        if(isset($user_uuid)) {
            $rows = \Magistraal\Database\query("SELECT * FROM magistraal_userdata WHERE user_uuid=?", $user_uuid);

            $settings = @json_decode($rows[0]['settings'] ?? '[]', true) ?? [];
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

        return \Magistraal\User\Settings\set_all($user_uuid, [$setting => $value]);
    }

    function set_all($user_uuid = null, $new_settings = null) {
        if(!isset($user_uuid) || !isset($new_settings)) {
            return false;
        }

        $db = \Magistraal\Database\connect();

        $settings = \Magistraal\User\Settings\get_all($user_uuid);
        $settings = array_replace($settings, $new_settings);
        $settings_encoded = json_encode($settings);

        $response = \Magistraal\Database\query(
            "REPLACE INTO magistraal_userdata (user_uuid, settings) VALUES (?, ?)", [$user_uuid, $settings_encoded]);

        return ($response > 0);
    }

    function call($url, $payload = []) {
        return \Magistraal\Browser\Browser::request($url, [
            'payload' => $payload
        ]);
    }
?>