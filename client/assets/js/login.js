$(document).on('magistraal.ready', function() {
    // Load tenants list
    magistraal.api.call('tenants/list').then((response) => {
        $.each(response.data, function(i, tenant) {
            magistraal.element.get('tenants-list').append(`<li data-search="${tenant.name}" style="display: none;" value="${tenant.id}">${tenant.name}</li>`);
        })
    });
})