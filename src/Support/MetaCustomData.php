<?php

namespace MohamedSaid\LaravelGa4Events\Support;

final class MetaCustomData
{
    private const DROPPED = ['event_id', 'page_path', 'page_location', 'debug_mode', 'engagement_time_msec', 'session_id'];

    public static function fromParams(array $params): array
    {
        $data = [];

        foreach ($params as $key => $value) {
            if (in_array($key, self::DROPPED, true) || $value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                $data[$key] = $value;
            }
        }

        if (isset($params['transaction_id']) && is_scalar($params['transaction_id'])) {
            $data['order_id'] = (string) $params['transaction_id'];
        }

        if (isset($params['item_name']) && is_scalar($params['item_name'])) {
            $data['content_name'] = (string) $params['item_name'];
        }

        if (isset($params['item_category']) && is_scalar($params['item_category'])) {
            $data['content_category'] = (string) $params['item_category'];
        }

        if (isset($params['currency']) && is_string($params['currency'])) {
            $data['currency'] = strtoupper($params['currency']);
        }

        $items = is_array($params['items'] ?? null) ? array_values(array_filter($params['items'], 'is_array')) : [];

        if ($items !== []) {
            $contents = array_values(array_filter(array_map(fn (array $item): ?array => isset($item['item_id']) ? array_filter([
                'id' => (string) $item['item_id'],
                'quantity' => (int) ($item['quantity'] ?? 1),
                'item_price' => isset($item['price']) ? (float) $item['price'] : null,
            ], fn (mixed $value): bool => $value !== null) : null, $items)));

            if ($contents !== []) {
                $data['contents'] = $contents;
                $data['content_ids'] = array_column($contents, 'id');
                $data['content_type'] = 'product';
                $data['num_items'] = array_sum(array_column($contents, 'quantity'));
            }
        } elseif (isset($params['item_id']) && is_scalar($params['item_id'])) {
            $data['content_ids'] = [(string) $params['item_id']];
            $data['content_type'] = 'product';
        }

        return $data;
    }
}
