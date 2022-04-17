<?php 
    namespace Magistraal\Settings;

    function get_all($category = null) {
        $category      = $category ?? 'root';
        $content       = @json_decode(@file_get_contents(ROOT.'/config/settings.json'), true);
        $user_settings = \Magistraal\User\Settings\get_all(\Magister\Session::$userUuid ?? null);

        if($category == 'root') {
            $items = $content['settings'];
        } else {
            $items = array_search_key($content['settings'], $category)['items'] ?? [];
        }
        
        foreach ($items as $item_key => &$item) {
            if(!isset($item['values'])) {
                continue;
            }

            $item['default'] = $content['default']["{$category}.{$item_key}"] ?? null;
            $item['value']   = $user_settings["{$category}.{$item_key}"] ?? null;
        }

        $result = [
            'category' => $category,
            'items'    => $items
        ];

        return $result;
    }
?>