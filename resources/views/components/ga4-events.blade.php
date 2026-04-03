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
                if (! config.injectMetaPixelScript || ! config.metaPixelId || window.__gtmEventsMetaPixelLoaded) {
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

                window.fbq('init', config.metaPixelId);
                window.fbq('track', 'PageView');
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

            const dispatchToDataLayer = (payload, source) => {
                window.dataLayer.push({
                    event: payload.name,
                    ...payload.params,
                });

                debug('INFO', `Event pushed to dataLayer from ${source}.`, payload);
            };

            const dispatchToMetaPixel = (payload, source) => {
                if (typeof window.fbq !== 'function') {
                    if (config.metaPixelId) {
                        debug('ERROR', `Meta Pixel is unavailable for ${source}.`, payload);
                    }

                    return;
                }

                const mappedEventName = typeof metaPixelEventMap[payload.name] === 'string' && metaPixelEventMap[payload.name].trim() !== ''
                    ? metaPixelEventMap[payload.name].trim()
                    : payload.name;
                const method = metaPixelStandardEvents.has(mappedEventName) ? 'track' : 'trackCustom';

                window.fbq(method, mappedEventName, payload.params);
                debug('INFO', `Event pushed to Meta Pixel from ${source}.`, {
                    name: mappedEventName,
                    method,
                    params: payload.params,
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

                dispatchToDataLayer(validatedPayload.payload, source);
                dispatchToMetaPixel(validatedPayload.payload, source);

                return {
                    ok: true,
                    errors: validatedPayload.errors,
                    payload: validatedPayload.payload,
                };
            };

            const track = (name, params = {}) => dispatch({ name, params }, 'api');

            const globalObjectName = String(config.globalJsObject || 'GTMEvents').trim() || 'GTMEvents';

            bootstrapGtmScript();
            bootstrapMetaPixel();

            window[globalObjectName] = {
                track,
                dispatch,
                config,
            };

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
