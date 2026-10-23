import './component/nova-search-tabs';
import './page/nova-search-analytics';
import './page/nova-search-rules';
import './nova-search.scss';

/**
 * Erweiterungen → Nova Suche: Auswertung, Synonyme, Weiterleitungen und Weg zu den Einstellungen.
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
        synonyms: {
            component: 'nova-search-rules',
            path: 'synonyms',
            meta: { ruleType: 'synonym' },
        },
        redirects: {
            component: 'nova-search-rules',
            path: 'redirects',
            meta: { ruleType: 'redirect' },
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
