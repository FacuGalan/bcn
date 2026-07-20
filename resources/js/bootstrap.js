import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Echo + Reverb (WebSockets para tiempo real multi-tenant)
import './echo.js';

// SortableJS (drag&drop para vista Kanban de pedidos)
import Sortable from 'sortablejs';
window.Sortable = Sortable;

// Componente Alpine kanbanBoard (registrado en alpine:init)
import './kanban.js';

// Componente Alpine domicilioMapa (picker de Google Maps del domicilio)
import './domicilio-mapa.js';

// Componente Alpine zonasMapa (mapa de zonas de entrega en config delivery)
import './zonas-mapa.js';

// Componente Alpine demoraAlerta (resaltado de pedidos demorados, tick local)
import './demora-alerta.js';

// Componente Alpine tiendaPreview (visor en vivo de la tienda online, RF-T12)
import './tienda-preview.js';

// Componente Alpine tiendaArticulos (drag & drop config por artículo RF-T14)
import './tienda-articulos.js';

// Modulo de impresion QZ Tray
import './qz-integration.js';

// Host de la pantalla orientada al cliente (segundo monitor en el cobro QR)
import './pantalla-cliente-host.js';
