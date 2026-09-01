import React from 'react';
import ReactDOM from 'react-dom/client';
import './i18n';
import '@phosphor-icons/web/regular';
import '@fontsource/inter/latin-400.css';
import '@fontsource/inter/latin-500.css';
import '@fontsource/inter/latin-600.css';
import '@fontsource/inter/latin-700.css';
import '@fontsource/syne/latin-700.css';
import '@nubitio/react-admin/style.css';
import { configureCore } from '@nubitio/react-admin';
import { App } from './App';

// App-wide formatting defaults. `currency` is what money fields assume on an
// empty create form — without it they fall back to EUR. Set all three to your
// market (e.g. locale: 'es-PE', timezone: 'America/Lima', currency: 'PEN').
configureCore({ locale: 'en-US', timezone: 'UTC', currency: 'USD' });

ReactDOM.createRoot(document.getElementById('root') as HTMLElement).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
