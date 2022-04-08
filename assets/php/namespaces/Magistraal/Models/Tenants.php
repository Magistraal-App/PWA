<?php 
    namespace Magistraal\Tenants;

    function search($query) {
        $tenants = @json_decode(@file_get_contents(ROOT.'/config/tenants.json'), true);

        $tenants = array_filter($tenants, function($tenant) use ($query) {
            return (stripos($tenant['name'], $query) !== false);
        });
        
        return array_values($tenants);
    }

    function get_all() {
        $tenants = @json_decode(@file_get_contents(ROOT.'/config/tenants.json'), true);
        return $tenants;
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