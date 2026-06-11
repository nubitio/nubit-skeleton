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
import { App } from './App';

ReactDOM.createRoot(document.getElementById('root') as HTMLElement).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
