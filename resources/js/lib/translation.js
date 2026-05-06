import useSiteStore from '../stores/useSiteStore';

/**
 * Get a translated field value from a content object (event, news article, project).
 * Falls back to the English version if Swahili is not available.
 */
export function getTranslation(item, field) {
    const language = useSiteStore.getState().language;
    if (!item) return '';
    if (language === 'sw' && item[`${field}_sw`]) return item[`${field}_sw`];
    return item[field] || '';
}
