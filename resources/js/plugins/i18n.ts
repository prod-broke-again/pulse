import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import ru from '@/locales/ru.json';

const savedLocale = typeof document !== 'undefined'
    ? (localStorage.getItem('locale') as 'en' | 'ru' | null) ?? null
    : null;

const browserLocale = typeof navigator !== 'undefined'
    ? (navigator.language.startsWith('ru') ? 'ru' : 'en')
    : 'en';

const locale = savedLocale ?? browserLocale;

export const i18n = createI18n({
    legacy: false,
    locale,
    fallbackLocale: 'en',
    messages: {
        en: en as Record<string, unknown>,
        ru: ru as Record<string, unknown>,
    },
});

export function setLocale(newLocale: 'en' | 'ru'): void {
    i18n.global.locale.value = newLocale;
    if (typeof document !== 'undefined') {
        localStorage.setItem('locale', newLocale);
    }
}
