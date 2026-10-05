import dayjs from './dayjs';

/**
 * The locale the app starts in. The i18n plugin loads the strings itself from
 * the value returned here, so this only aligns dayjs and the document.
 */
export const bootLocale = (props: Record<string, unknown>): string => {
    const locale = (props.locale as string | undefined) ?? 'en';

    dayjs.locale(locale.toLowerCase());
    document.documentElement.lang = locale;

    return locale;
};

export const i18nConfig = (lang: string) => ({
    lang,
    resolve: async (locale: string) => {
        const langs = import.meta.glob('../../lang/*.json');

        return await langs[`../../lang/php_${locale}.json`]();
    },
});
