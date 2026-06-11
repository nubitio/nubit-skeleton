import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';
import { initCoreI18n } from '@nubitio/react-admin';

void i18next.use(initReactI18next).init({
  lng: 'en',
  fallbackLng: 'en',
  ns: ['core'],
  defaultNS: 'core',
  resources: {},
  interpolation: { escapeValue: false },
});

// Registers the built-in en/es bundles for the CRUD engine and admin shell.
initCoreI18n();
