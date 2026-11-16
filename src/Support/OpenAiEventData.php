<?php

namespace MohamedSaid\LaravelGa4Events\Support;

final class OpenAiEventData
{
    public const ZERO_DECIMAL_CURRENCIES = ['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public const THREE_DECIMAL_CURRENCIES = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

    private const CONTENTS_EVENTS = ['checkout_started', 'contents_viewed', 'items_added', 'order_created'];

    private const PLAN_EVENTS = ['subscription_created', 'trial_started'];

    public static function dataType(string $type): string
    {
        return match (true) {
            in_array($type, self::CONTENTS_EVENTS, true) => 'contents',
            in_array($type, self::PLAN_EVENTS, true) => 'plan_enrollment',
            EventNameMapper::isOpenAiStandardEvent($type) => 'customer_action',
            default => 'custom',
        };
    }

    public static function fromParams(string $type, array $params): array
    {
        $dataType = self::dataType($type);
        $currency = self::currency($params['currency'] ?? null);
        $amount = $currency !== null ? self::minorUnits($params['value'] ?? null, $currency) : null;

        $data = [
            'type' => $dataType,
            'amount' => $amount,
            'currency' => $amount !== null ? $currency : null,
        ];

        if (in_array($dataType, ['plan_enrollment', 'custom'], true)) {
            $planId = $params['plan_id'] ?? $params['item_id'] ?? null;
            $data['plan_id'] = is_scalar($planId) && (string) $planId !== '' ? (string) $planId : null;
        }

        if ($dataType !== 'customer_action') {
            $contents = self::contents($params, $currency);
            $data['contents'] = $contents !== [] ? $contents : null;
        }

        return array_filter($data, fn (mixed $value): bool => $value !== null);
    }

    public static function contents(array $params, ?string $currency): array
    {
        $items = is_array($params['items'] ?? null) ? array_values(array_filter($params['items'], 'is_array')) : [];

        if ($items === [] && isset($params['item_id']) && is_scalar($params['item_id'])) {
            $items = [[
                'item_id' => $params['item_id'],
                'item_name' => $params['item_name'] ?? null,
                'item_category' => $params['item_category'] ?? null,
                'quantity' => 1,
            ]];
        }

        return array_values(array_filter(array_map(function (array $item) use ($currency): ?array {
            if (! isset($item['item_id']) || ! is_scalar($item['item_id']) || (string) $item['item_id'] === '') {
                return null;
            }

            $itemCurrency = self::currency($item['currency'] ?? null) ?? $currency;
            $amount = $itemCurrency !== null ? self::minorUnits($item['price'] ?? null, $itemCurrency) : null;

            return array_filter([
                'id' => (string) $item['item_id'],
                'group_id' => isset($item['item_group_id']) && is_scalar($item['item_group_id']) ? (string) $item['item_group_id'] : null,
                'name' => isset($item['item_name']) && is_scalar($item['item_name']) && (string) $item['item_name'] !== '' ? (string) $item['item_name'] : null,
                'content_type' => isset($item['item_category']) && is_scalar($item['item_category']) && (string) $item['item_category'] !== '' ? (string) $item['item_category'] : null,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'amount' => $amount,
                'currency' => $amount !== null ? $itemCurrency : null,
            ], fn (mixed $value): bool => $value !== null);
        }, $items)));
    }

    public static function minorUnits(mixed $value, string $currency): ?int
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        $exponent = match (true) {
            in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true) => 0,
            in_array($currency, self::THREE_DECIMAL_CURRENCIES, true) => 3,
            default => 2,
        };

        return (int) round((float) $value * (10 ** $exponent));
    }

    private static function currency(mixed $currency): ?string
    {
        if (! is_string($currency) || preg_match('/^[A-Za-z]{3}$/', trim($currency)) !== 1) {
            return null;
        }

        return strtoupper(trim($currency));
    }
}
