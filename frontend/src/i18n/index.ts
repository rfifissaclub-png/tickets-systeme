import i18next from 'i18next'
import { initReactI18next } from 'react-i18next'

import { useLocaleStore } from '@/stores/locale-store'

import en from './locales/en.json'
import fr from './locales/fr.json'

void i18next.use(initReactI18next).init({
  resources: {
    en: { translation: en },
    fr: { translation: fr },
  },
  lng: useLocaleStore.getState().locale,
  fallbackLng: 'en',
  interpolation: { escapeValue: false },
})

useLocaleStore.subscribe((state) => {
  void i18next.changeLanguage(state.locale)
})

export default i18next
