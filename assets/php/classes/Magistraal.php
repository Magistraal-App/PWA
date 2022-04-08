<?php 
    namespace Magistraal;

    class Magistraal {
        private $Magister;
        private $token;

        public function __construct($Magister) {
            $this->Magister = $Magister;
            $this->token    = $Magister->token;
        }

        public function listSettings($category = null, $sub_category = null) {
            $settings = @json_decode(@file_get_contents(API.'/config/settings.json'), true);
            return $settings[$category][$sub_category] ?? $settings[$category] ?? $settings;
        }
    }
?>