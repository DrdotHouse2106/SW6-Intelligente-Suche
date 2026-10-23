import './page/nova-search-analytics';

/**
 * Erweiterungen → Nova Suche: Auswertung und schneller Weg zu den Einstellungen.
 */
Shopware.Module.register('nova-search', {
    type: 'plugin',
    name: 'nova-search',
    title: 'nova-search.general.title',
    description: 'nova-search.general.description',
    color: '#189eff',
    icon: 'regular-search',

    routes: {
        index: {
            component: 'nova-search-analytics',
            path: 'index',
        },
    },

    navigation: [{
        id: 'nova-search',
        label: 'nova-search.general.title',
        path: 'nova.search.index',
        parent: 'sw-extension',
        position: 100,
    }],
});
