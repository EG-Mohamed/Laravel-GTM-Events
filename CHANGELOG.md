# Changelog

All notable changes to `Laravel-Ga4-Events` will be documented in this file.

## 2.0.0 - 2026-11-16

- Server-side tracking: Meta Conversions API and GA4 Measurement Protocol drivers, delivered by a queued job with retries.
- One `event_id` per event, shared by browser and server, so Meta deduplicates and GA4 deduplicates purchases by `transaction_id`.
- Canonical `event_map` that maps GA4 names to Meta standard events (`purchase` → `Purchase`, `sign_up` → `CompleteRegistration`, …). A destination can be set to `false` to skip it.
- `CaptureTrackingContext` middleware: captures `_fbp`, `_fbc` (built from `fbclid`), `_ga` client/session ids, `gclid`, UTMs, IP and user agent, and sets first-party `_fbp`/`_fbc` cookies.
- `HasTrackingIdentity` trait + `ga4_tracking_identities` table, so later events (webhooks, admin actions, queues) keep the user's attribution.
- `ga4_server_events` delivery log.
- Fluent `LaravelGa4Events::event()` builder, `TracksEvents` Livewire trait, session-queued browser events that survive redirects.
- Optional `POST /ga4-events/collect` relay (allowlisted, throttled) and `GTMEvents.track(name, params, { server: true, eventId })`.
- Meta Pixel: `eventID` on every call, GA4 `items` mapped to `contents`/`content_ids`, hashed advanced matching for logged-in users.

## 1.6.2 - 2026-03-31

**Full Changelog**: https://github.com/EG-Mohamed/Laravel-GTM-Events/compare/1.6.1...1.6.2

## 1.6.1 - 2026-03-31

**Full Changelog**: https://github.com/EG-Mohamed/Laravel-GTM-Events/compare/1.6...1.6.1

**Full Changelog**: https://github.com/EG-Mohamed/Laravel-GTM-Events/compare/1.0...1.6.1

## 1.0 - 2026-03-29

**Full Changelog**: https://github.com/EG-Mohamed/Laravel-Ga4-Events/commits/1.0
