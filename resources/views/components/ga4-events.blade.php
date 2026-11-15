@if ($ga4Config['enabled'])
    @if ($ga4Config['injectMetaPixelScript'] && $ga4Config['metaPixelId'])
        <noscript>
            <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $ga4Config['metaPixelId'] }}&ev=PageView&noscript=1" alt="" />
        </noscript>
    @endif

    <script id="gtm-events-config" type="application/json">{!! json_encode($ga4Config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    <script>
        (() => {
            const readConfig = () => {
                const element = document.getElementById('gtm-events-config');

                if (! element) {
                    return {};
                }

                try {
                    return JSON.parse(element.textContent || '{}');
                } catch {
                    return {};
                }
            };

            const config = readConfig();

            window.dataLayer = window.dataLayer || [];

            const livewireListeners = new Set();
            const metaPixelEventMap = typeof config.metaPixelEventMap === 'object' && config.metaPixelEventMap !== null
                ? config.metaPixelEventMap
                : {};
            const metaPixelStandardEvents = new Set(Array.isArray(config.metaPixelStandardEvents) ? config.metaPixelStandardEvents : []);
            const eventMap = typeof config.eventMap === 'object' && config.eventMap !== null ? config.eventMap : {};
            const consentGranted = config.consent !== false;

            const generateEventId = () => {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return window.crypto.randomUUID();
                }

                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
                    const random = Math.random() * 16 | 0;

                    return (character === 'x' ? random : (random & 0x3 | 0x8)).toString(16);
                });
            };

            const resolveDestinationName = (name, destination) => {
                const mapped = eventMap[name] ? eventMap[name][destination] : undefined;

                if (mapped === false) {
                    return null;
                }

                if (typeof mapped === 'string' && mapped.trim() !== '') {
                    return mapped.trim();
                }

                if (destination === 'meta' && typeof metaPixelEventMap[name] === 'string' && metaPixelEventMap[name].trim() !== '') {
                    return metaPixelEventMap[name].trim();
                }

                return name;
            };

            const toMetaParams = (params) => {
                const dropped = ['page_path', 'page_location', 'debug_mode', 'engagement_time_msec', 'session_id', 'event_id'];
                const output = {};

                Object.entries(params || {}).forEach(([key, value]) => {
                    if (dropped.includes(key) || value === null || value === '' || typeof value === 'object') {
                        return;
                    }

                    output[key] = value;
                });

                if (params.transaction_id) {
                    output.order_id = String(params.transaction_id);
                }

                if (params.item_name) {
                    output.content_name = String(params.item_name);
                }

                if (params.item_category) {
                    output.content_category = String(params.item_category);
                }

                if (typeof params.currency === 'string') {
                    output.currency = params.currency.toUpperCase();
                }

                const items = Array.isArray(params.items) ? params.items.filter((item) => item && typeof item === 'object' && item.item_id !== undefined) : [];

                if (items.length > 0) {
                    output.contents = items.map((item) => {
                        const content = { id: String(item.item_id), quantity: Number(item.quantity || 1) };

                        if (item.price !== undefined) {
                            content.item_price = Number(item.price);
                        }

                        return content;
                    });
                    output.content_ids = output.contents.map((content) => content.id);
                    output.content_type = 'product';
                    output.num_items = output.contents.reduce((total, content) => total + content.quantity, 0);
                } else if (params.item_id !== undefined) {
                    output.content_ids = [String(params.item_id)];
                    output.content_type = 'product';
                }

                return output;
            };

            const debug = (level, message, context = null) => {
                if (! config.debug) {
                    return;
                }

                const payload = [config.consolePrefix || '[GTM Events]', `[${level}]`, message];

                if (context === null) {
                    console.log(...payload);

                    return;
                }

                console.log(...payload, context);
            };

            const bootstrapGtmScript = () => {
                if (! config.injectGtmScript || ! config.containerId || window.__gtmEventsScriptLoaded) {
                    return;
                }

                window.__gtmEventsScriptLoaded = true;
                window.dataLayer.push({
                    'gtm.start': new Date().getTime(),
                    event: 'gtm.js',
                });

                const firstScript = document.getElementsByTagName('script')[0];
                const script = document.createElement('script');

                script.async = true;
                script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(config.containerId)}`;

                if (firstScript?.parentNode) {
                    firstScript.parentNode.insertBefore(script, firstScript);

                    return;
                }

                (document.head || document.body || document.documentElement).appendChild(script);
            };

            const bootstrapMetaPixel = () => {
                if (! consentGranted || ! config.injectMetaPixelScript || ! config.metaPixelId || window.__gtmEventsMetaPixelLoaded) {
                    return;
                }

                window.__gtmEventsMetaPixelLoaded = true;

                ((f, b, e, v, n, t, s) => {
                    if (f.fbq) {
                        return;
                    }

                    n = f.fbq = (...args) => {
                        if (n.callMethod) {
                            n.callMethod.apply(n, args);

                            return;
                        }

                        n.queue.push(args);
                    };

                    if (! f._fbq) {
                        f._fbq = n;
                    }

                    n.push = n;
                    n.loaded = true;
                    n.version = '2.0';
                    n.queue = [];
                    t = b.createElement(e);
                    t.async = true;
                    t.src = v;
                    s = b.getElementsByTagName(e)[0];

                    if (s?.parentNode) {
                        s.parentNode.insertBefore(t, s);

                        return;
                    }

                    (b.head || b.body || b.documentElement).appendChild(t);
                })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

                const advancedMatching = config.metaAdvancedMatching && typeof config.metaAdvancedMatching === 'object'
                    ? config.metaAdvancedMatching
                    : {};

                if (Object.keys(advancedMatching).length > 0) {
                    window.fbq('init', config.metaPixelId, advancedMatching);
                } else {
                    window.fbq('init', config.metaPixelId);
                }

                window.fbq('track', 'PageView', {}, { eventID: generateEventId() });
            };

            const pattern = (() => {
                const source = String(config.allowedNamePattern || '').trim();

                if (source === '') {
                    return /^[a-zA-Z][a-zA-Z0-9_]*$/;
                }

                const match = source.match(/^\/(.*)\/([gimsuy]*)$/);

                try {
                    if (match) {
                        return new RegExp(match[1], match[2]);
                    }

                    return new RegExp(source);
                } catch {
                    return /^[a-zA-Z][a-zA-Z0-9_]*$/;
                }
            })();

            const normalizeIncomingPayload = (payload) => {
                if (! payload) {
                    return {};
                }

                if (Array.isArray(payload)) {
                    return payload.length === 1 && payload[0] && typeof payload[0] === 'object'
                        ? payload[0]
                        : {};
                }

                if (typeof payload !== 'object') {
                    return {};
                }

                if ('name' in payload || 'params' in payload) {
                    return payload;
                }

                if ('payload' in payload && payload.payload && typeof payload.payload === 'object') {
                    return payload.payload;
                }

                if ('0' in payload && payload[0] && typeof payload[0] === 'object') {
                    return payload[0];
                }

                return payload;
            };

            const normalizeValue = (value, errors, depth = 0) => {
                const maxDepth = Number(config.maxParamNesting || 4);
                const maxLength = Number(config.maxParamValueLength || 100);

                if (typeof value === 'string') {
                    if (value.length > maxLength) {
                        errors.push('Event param value exceeds the allowed length.');

                        return value.slice(0, maxLength);
                    }

                    return value;
                }

                if (typeof value === 'number' || typeof value === 'boolean' || value === null) {
                    return value;
                }

                if (Array.isArray(value)) {
                    if (depth >= maxDepth) {
                        errors.push('Event param nesting exceeds the allowed depth.');

                        return [];
                    }

                    return value.map((item) => normalizeValue(item, errors, depth + 1));
                }

                if (typeof value === 'object') {
                    if (depth >= maxDepth) {
                        errors.push('Event param nesting exceeds the allowed depth.');

                        return {};
                    }

                    return Object.entries(value).reduce((carry, [key, item]) => {
                        const normalizedKey = String(key).trim().replace(/\s+/g, '_');

                        carry[normalizedKey] = normalizeValue(item, errors, depth + 1);

                        return carry;
                    }, {});
                }

                errors.push('Event param value type is not supported.');

                return null;
            };

            const validatePayload = (payload) => {
                const errors = [];
                const name = typeof payload?.name === 'string' ? payload.name.trim() : '';
                const maxEventNameLength = Number(config.maxEventNameLength || 40);

                if (name === '') {
                    errors.push('Event name is required.');
                }

                if (name.length > maxEventNameLength) {
                    errors.push('Event name exceeds the allowed length.');
                }

                if (name !== '' && ! pattern.test(name)) {
                    errors.push('Event name does not match the allowed pattern.');
                }

                const rawParams = payload?.params ?? {};

                if (rawParams === null || typeof rawParams !== 'object' || Array.isArray(rawParams)) {
                    errors.push('Event params must be an object.');

                    return {
                        valid: errors.length === 0,
                        errors,
                        payload: {
                            name,
                            params: {},
                        },
                    };
                }

                const maxParams = Number(config.maxParams || 25);
                const maxParamKeyLength = Number(config.maxParamKeyLength || 40);
                const params = {};

                Object.entries(rawParams).slice(0, maxParams).forEach(([key, value]) => {
                    const normalizedKey = String(key).trim().replace(/\s+/g, '_');

                    if (normalizedKey === '') {
                        errors.push('Event param key cannot be empty.');

                        return;
                    }

                    if (normalizedKey.length > maxParamKeyLength) {
                        errors.push('Event param key exceeds the allowed length.');

                        return;
                    }

                    const normalizedValue = normalizeValue(value, errors);

                    if (normalizedValue === null && value !== null) {
                        return;
                    }

                    params[normalizedKey] = normalizedValue;
                });

                if (Object.keys(rawParams).length > maxParams) {
                    errors.push('Event params exceed the maximum allowed count.');
                }

                return {
                    valid: errors.length === 0,
                    errors,
                    payload: {
                        name,
                        params,
                    },
                };
            };

            const dispatchToDataLayer = (payload, eventId, source) => {
                const ga4Name = resolveDestinationName(payload.name, 'ga4');

                if (ga4Name === null) {
                    return;
                }

                window.dataLayer.push({
                    event: ga4Name,
                    event_id: eventId,
                    ...payload.params,
                });

                debug('INFO', `Event pushed to dataLayer from ${source}.`, { ...payload, name: ga4Name, event_id: eventId });
            };

            const dispatchToMetaPixel = (payload, eventId, source) => {
                const mappedEventName = resolveDestinationName(payload.name, 'meta');

                if (mappedEventName === null) {
                    return;
                }

                if (typeof window.fbq !== 'function') {
                    if (config.metaPixelId) {
                        debug('ERROR', `Meta Pixel is unavailable for ${source}.`, payload);
                    }

                    return;
                }

                const method = metaPixelStandardEvents.has(mappedEventName) ? 'track' : 'trackCustom';
                const metaParams = toMetaParams(payload.params);

                window.fbq(method, mappedEventName, metaParams, { eventID: eventId });
                debug('INFO', `Event pushed to Meta Pixel from ${source}.`, {
                    name: mappedEventName,
                    method,
                    eventID: eventId,
                    params: metaParams,
                });
            };

            const dispatchToServer = (payload, eventId, source) => {
                if (! consentGranted || ! config.collectUrl || typeof window.fetch !== 'function') {
                    return;
                }

                const headers = {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                };
                const metaToken = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = (metaToken && metaToken.getAttribute('content')) || config.csrfToken;

                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }

                window.fetch(config.collectUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: true,
                    headers,
                    body: JSON.stringify({
                        name: payload.name,
                        event_id: eventId,
                        params: payload.params,
                        url: window.location.href,
                    }),
                }).then(() => {
                    debug('INFO', `Event relayed to server from ${source}.`, { name: payload.name, event_id: eventId });
                }).catch(() => {
                    debug('ERROR', `Server relay failed from ${source}.`, { name: payload.name, event_id: eventId });
                });
            };

            const dispatch = (payload, source = 'manual') => {
                const normalizedPayload = normalizeIncomingPayload(payload);
                const validatedPayload = validatePayload(normalizedPayload);

                if (! validatedPayload.valid) {
                    debug('ERROR', `Invalid GTM payload from ${source}.`, validatedPayload.errors);

                    if (config.dropInvalidEvents !== false) {
                        return {
                            ok: false,
                            errors: validatedPayload.errors,
                        };
                    }
                }

                const eventId = typeof normalizedPayload.event_id === 'string' && normalizedPayload.event_id.trim() !== ''
                    ? normalizedPayload.event_id.trim()
                    : generateEventId();

                dispatchToDataLayer(validatedPayload.payload, eventId, source);
                dispatchToMetaPixel(validatedPayload.payload, eventId, source);

                if (normalizedPayload.server === true) {
                    dispatchToServer(validatedPayload.payload, eventId, source);
                }

                return {
                    ok: true,
                    errors: validatedPayload.errors,
                    payload: validatedPayload.payload,
                    eventId,
                };
            };

            const track = (name, params = {}, options = {}) => dispatch({
                name,
                params,
                event_id: options && typeof options.eventId === 'string' ? options.eventId : undefined,
                server: Boolean(options && options.server),
            }, 'api');

            const globalObjectName = String(config.globalJsObject || 'GTMEvents').trim() || 'GTMEvents';

            bootstrapGtmScript();
            bootstrapMetaPixel();

            window[globalObjectName] = {
                track,
                dispatch,
                generateEventId,
                config,
            };

            (Array.isArray(config.pendingEvents) ? config.pendingEvents : []).forEach((pendingEvent) => {
                dispatch(pendingEvent, 'server-queue');
            });

            if (config.eventBusName) {
                window.addEventListener(config.eventBusName, (event) => {
                    dispatch(event.detail, 'dom');
                });
            }

            const bindLivewireListener = () => {
                if (! config.livewireEventName || livewireListeners.has(config.livewireEventName)) {
                    return;
                }

                if (! window.Livewire || typeof window.Livewire.on !== 'function') {
                    return;
                }

                livewireListeners.add(config.livewireEventName);
                window.Livewire.on(config.livewireEventName, (payload) => {
                    dispatch(payload, 'livewire');
                });
            };

            document.addEventListener('livewire:init', bindLivewireListener);
            bindLivewireListener();
            debug('INFO', 'GTM bridge initialized.', config);
        })();
    </script>
@endif
