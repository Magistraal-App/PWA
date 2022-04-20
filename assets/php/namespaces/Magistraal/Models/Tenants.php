<?php 
    namespace Magistraal\Tenants;

    function search($query) {
        $response = \Magistraal\Browser\Browser::request("https://accounts.magister.net/challenges/tenant/search?key={$query}");
        $result   = [];

        if(!is_array($response['body'])) {
            return [];
        }
         
        foreach ($response['body'] as $tenant) {
            $result[] = ['name' => $tenant['displayName'], 'id' => $tenant['id']];
        }

        return $result;
    }

    function get($tenant_id) {
        $tenants           = \Magistraal\Tenants\get_all();
        $tenant_names      = array_column($tenants, 'name', 'id');
        $tenant_subdomains = array_column($tenants, 'subdomain', 'id');

        if(!isset($tenant_names[$tenant_id]) || !isset($tenant_subdomains[$tenant_id])) {
            return null;
        }

        return ['name' => $tenant_names[$tenant_id], 'subdomain' => $tenant_subdomains[$tenant_id]];
    }
?>