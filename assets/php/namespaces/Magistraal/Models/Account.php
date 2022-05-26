<?php 
    namespace Magistraal\Account;

    function get_all() {
        return \Magistraal\Account\format_all(\Magister\Session::accountList());
    }

    function format_all($account) {
        $formatted = [
            'personal' => [
                'uuid'             => $account['personal']['UuId'] ?? null,
                'id'               => $account['personal']['Persoon']['Id'] ?? null,
                'name'             => $account['personal']['Persoon']['Roepnaam'] ?? null,
                'infix'            => $account['personal']['Persoon']['Tussenvoegsel'] ?? '',
                'surname'          => $account['personal']['Persoon']['Achternaam'] ?? null,
                'name_official'    => $account['personal']['Persoon']['OfficieleVoornamen'] ?? null,
                'infix_official'   => $account['personal']['Persoon']['OfficieleTussenvoegsels'] ?? '',
                'surname_official' => $account['personal']['Persoon']['OfficieleAchternaam'] ?? null,
                'birth_date'       => date('c', strtotime($account['personal']['Persoon']['Geboortedatum']))
            ],
            'contact' => [
                'phone' => $account['contact']['Mobiel'] ?? null,
                'email' => $account['contact']['EmailAdres'] ?? null
            ],
            'residences' => []
        ];

        foreach ($account['residences'] as $residence) {
            if(isset($residence['isGeheim']) && $residence['isGeheim'] == true) {
                continue;
            }

            $formatted['residences'][] = [
                'street'               => \Magistraal\Account\format_address_item($residence['straat'] ?? null, 'street'),
                'street_number'        => \Magistraal\Account\format_address_item($residence['huisnummer'] ?? null, 'street_number'),
                'street_number_suffix' => \Magistraal\Account\format_address_item($residence['toevoeging'] ?? '', 'street_number_suffix'),
                'postal_code'          => \Magistraal\Account\format_address_item($residence['postcode'] ?? null, 'postal_code'),
                'place'                => \Magistraal\Account\format_address_item($residence['plaats'] ?? null, 'place'),
                'country'              => \Magistraal\Account\format_address_item($residence['land'] ?? null, 'country'),
                'type'                 => strtolower($residence['type']) == 'woon' ? 'live' : 'postal'
            ];
        }

        $formatted['personal']['full_name'] = $formatted['personal']['name'].' '.(isset($formatted['personal']['infix']) ? $formatted['personal']['infix'].' ' : '').$formatted['personal']['surname'];
        $formatted['personal']['full_name_official'] = $formatted['personal']['name_official'].' '.(isset($formatted['personal']['infix_official']) ? $formatted['personal']['infix_official'].' ' : '').$formatted['personal']['surname_official'];

        return $formatted;
    }

    function format_address_item($item, $type) {
        $item = trim(strtolower($item));

        switch($type) {
            case 'street':
                return ucwords(preg_replace('/\.(?! )/', '. ', $item));
            case 'postal_code':
                return strtoupper(substr($item, 0, 4).' '.substr($item, -2, 2));
            case 'place':
                return ucwords($item);
            case 'country':
                return ucwords($item);
        }

        return $item;
    }
?>