<?php

if (! function_exists('api_response')) {
    /**
     * Build a JSON success or error payload for the API.
     */
    function api_response(
        mixed $data = [],
        string $message = 'Success',
        int $status = 200,
        bool $success = true,
        array $meta = []
    ): array {
        $payload = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return $payload;
    }
}
