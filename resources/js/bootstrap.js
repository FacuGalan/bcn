import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Echo + Reverb (WebSockets para tiempo real multi-tenant)
import './echo.js';

// SortableJS (drag&drop para vista Kanban de pedidos)
import Sortable from 'sortablejs';
window.Sortable = Sortable;

// Modulo de impresion QZ Tray
import './qz-integration.js';
