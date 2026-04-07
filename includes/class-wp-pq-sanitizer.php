<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Sanitizer
{
    public static function text(WP_REST_Request $request, string $key): string
    {
        return sanitize_text_field((string) $request->get_param($key));
    }

    public static function key(WP_REST_Request $request, string $key): string
    {
        return sanitize_key((string) $request->get_param($key));
    }

    public static function email(WP_REST_Request $request, string $key): string
    {
        return sanitize_email((string) $request->get_param($key));
    }

    public static function textarea(WP_REST_Request $request, string $key): string
    {
        return sanitize_textarea_field((string) $request->get_param($key));
    }

    public static function int(WP_REST_Request $request, string $key): int
    {
        return (int) $request->get_param($key);
    }

    public static function bool(WP_REST_Request $request, string $key): bool
    {
        return (bool) $request->get_param($key);
    }
}
