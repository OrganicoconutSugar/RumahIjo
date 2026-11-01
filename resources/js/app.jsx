import React from 'react';
import {createRoot} from 'react-dom/client';
import {createInertiaApp} from '@inertiajs/react';
createInertiaApp({resolve: name => ({Landing: () => import('../../src/main.jsx'), Login: () => import('./Pages/Login.jsx'), Dashboard: () => import('./Pages/Dashboard.jsx'), Ticket: () => import('./Pages/Ticket.jsx'), PaymentDetail: () => import('./Pages/PaymentDetail.jsx'), Receipt: () => import('./Pages/Receipt.jsx')})[name](),setup({el,App,props}) {createRoot(el).render(<App {...props}/>);}});
