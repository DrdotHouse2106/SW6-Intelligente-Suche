/*
 * Nova Suche – Storefront-Einstiegspunkt
 * Das Plugin wird erst geladen, wenn das Suchformular der Sofortsuche existiert.
 */
window.PluginManager.register(
    'NovaSearch',
    () => import('./plugin/nova-search.plugin'),
    '[data-nova-search]'
);
