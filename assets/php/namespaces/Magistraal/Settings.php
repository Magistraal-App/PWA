<?php 
    namespace Magistraal\Settings;

    function get_all($category = null) {
        $settings = @json_decode(@file_get_contents(ROOT.'/config/settings.json'), true);

        if(is_null($category)) {
            return ['category' => 'root', 'items' => $settings];
        }

        $category_info = array_search_key($settings, $category);

        $category_info['category'] = $category;

        return $category_info;
    }
?>