<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_AI_Importer
{
    public static function parse_document(array $args)
    {
        $api_key = trim((string) ($args['api_key'] ?? ''));
        if ($api_key === '') {
            return new WP_Error('pq_openai_missing_key', 'Add an OpenAI API key in Switchboard Settings before using the document ingester.');
        }

        $model = trim((string) ($args['model'] ?? 'gpt-4o-mini'));
        $client_name = trim((string) ($args['client_name'] ?? 'Client'));
        $known_jobs = array_values(array_filter(array_map('trim', (array) ($args['known_jobs'] ?? []))));
        $source_text = trim((string) ($args['source_text'] ?? ''));
        $file_path = trim((string) ($args['file_path'] ?? ''));
        $file_name = trim((string) ($args['file_name'] ?? ''));
        $mime_type = trim((string) ($args['mime_type'] ?? ''));

        if ($source_text === '' && $file_path === '') {
            return new WP_Error('pq_ai_empty_source', 'Paste a task list or upload a document to parse.');
        }

        $request = self::build_request_payload($model, $client_name, $known_jobs, $source_text, $file_path, $file_name, $mime_type);

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($request),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('pq_openai_request_failed', 'OpenAI request failed: ' . $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $message = self::extract_error_message($payload);
            return new WP_Error('pq_openai_http_error', 'OpenAI returned an error' . ($message !== '' ? ': ' . $message : '.'));
        }

        if (! empty($payload['error'])) {
            $message = self::extract_error_message($payload);
            return new WP_Error('pq_openai_error', 'OpenAI could not parse this document' . ($message !== '' ? ': ' . $message : '.'));
        }

        $json = trim((string) ($payload['output_text'] ?? ''));
        if ($json === '') {
            $json = self::extract_output_text($payload);
        }

        if ($json === '') {
            return new WP_Error('pq_openai_empty', 'OpenAI returned an empty parse response.');
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return new WP_Error('pq_openai_bad_json', 'OpenAI returned a response, but it was not valid JSON.');
        }

        return self::normalize_result($data, $client_name, $known_jobs);
    }

    private static function build_request_payload(string $model, string $client_name, array $known_jobs, string $source_text, string $file_path, string $file_name, string $mime_type): array
    {
        $job_guidance = empty($known_jobs)
            ? 'No jobs exist yet for this client. Suggest a sensible job_name for each task.'
            : 'Known jobs for this client: ' . implode(', ', $known_jobs) . '. Reuse these exact names when they fit; only invent a new job_name if the task clearly belongs elsewhere.';

        $instructions = implode("\n", [
            'You extract task lists into strict JSON for a project management system.',
            'Return JSON only.',
            'The client account is: ' . $client_name . '.',
            $job_guidance,
            'If the source marks responsibility with tags like [READ], [NIKKI], [BOTH], preserve that in action_owner_hint.',
            'Map priority into one of: low, normal, high, urgent.',
            'If no deadline is present, use an empty string.',
            'If billable status is unclear, set is_billable to null.',
            'If the source marks an item done/completed, capture that in status_hint; otherwise status_hint should usually be pending_approval.',
            'Keep titles concise and descriptions helpful.',
        ]);

        $content = [
            [
                'type' => 'input_text',
                'text' => $instructions,
            ],
        ];

        if ($source_text !== '') {
            $content[] = [
                'type' => 'input_text',
                'text' => "Source text:\n" . $source_text,
            ];
        }

        if ($file_path !== '' && is_readable($file_path)) {
            $max_bytes = 20 * 1024 * 1024;
            if (filesize($file_path) > $max_bytes) {
                return new \WP_Error('pq_file_too_large', 'File exceeds the 20 MB limit for AI import.', ['status' => 413]);
            }
            $encoded = base64_encode((string) file_get_contents($file_path));
            $safe_name = $file_name !== '' ? $file_name : basename($file_path);

            if ($mime_type === 'application/pdf' || str_ends_with(strtolower($safe_name), '.pdf')) {
                $content[] = [
                    'type' => 'input_file',
                    'filename' => $safe_name,
                    'file_data' => 'data:application/pdf;base64,' . $encoded,
                ];
            } else {
                $text = self::extract_text_from_non_pdf_upload($file_path);
                if ($text !== '') {
                    $content[] = [
                        'type' => 'input_text',
                        'text' => "Uploaded file (" . $safe_name . "):\n" . $text,
                    ];
                }
            }
        }

        return [
            'model' => $model,
            'input' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'priority_queue_import',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'summary' => [
                                'type' => 'string',
                            ],
                            'tasks' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'description' => ['type' => 'string'],
                                        'job_name' => ['type' => 'string'],
                                        'priority' => [
                                            'type' => 'string',
                                            'enum' => ['low', 'normal', 'high', 'urgent'],
                                        ],
                                        'requested_deadline' => ['type' => 'string'],
                                        'needs_meeting' => ['type' => 'boolean'],
                                        'action_owner_hint' => ['type' => 'string'],
                                        'is_billable' => [
                                            'anyOf' => [
                                                ['type' => 'boolean'],
                                                ['type' => 'null'],
                                            ],
                                        ],
                                        'status_hint' => ['type' => 'string'],
                                    ],
                                    'required' => [
                                        'title',
                                        'description',
                                        'job_name',
                                        'priority',
                                        'requested_deadline',
                                        'needs_meeting',
                                        'action_owner_hint',
                                        'is_billable',
                                        'status_hint',
                                    ],
                                ],
                            ],
                        ],
                        'required' => ['summary', 'tasks'],
                    ],
                ],
            ],
        ];
    }

    private static function extract_text_from_non_pdf_upload(string $file_path): string
    {
        $contents = (string) @file_get_contents($file_path);
        return trim(wp_strip_all_tags($contents));
    }

    private static function extract_error_message($payload): string
    {
        if (! is_array($payload)) {
            return '';
        }

        if (! empty($payload['error']['message'])) {
            return (string) $payload['error']['message'];
        }

        return '';
    }

    private static function extract_output_text(array $payload): string
    {
        $output = (array) ($payload['output'] ?? []);
        foreach ($output as $item) {
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && ! empty($content['text'])) {
                    return trim((string) $content['text']);
                }
            }
        }

        return '';
    }

    private static function normalize_result(array $data, string $client_name, array $known_jobs): array
    {
        $tasks = [];
        foreach ((array) ($data['tasks'] ?? []) as $row) {
            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $job_name = sanitize_text_field((string) ($row['job_name'] ?? ''));
            $priority = strtolower(sanitize_key((string) ($row['priority'] ?? 'normal')));
            if (! in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
                $priority = 'normal';
            }

            $tasks[] = [
                'title' => $title,
                'description' => sanitize_textarea_field((string) ($row['description'] ?? '')),
                'job_name' => $job_name,
                'job_exists' => $job_name !== '' && in_array($job_name, $known_jobs, true),
                'priority' => $priority,
                'requested_deadline' => sanitize_text_field((string) ($row['requested_deadline'] ?? '')),
                'needs_meeting' => ! empty($row['needs_meeting']),
                'action_owner_hint' => sanitize_text_field((string) ($row['action_owner_hint'] ?? '')),
                'is_billable' => array_key_exists('is_billable', $row) ? $row['is_billable'] : null,
                'status_hint' => sanitize_key((string) ($row['status_hint'] ?? 'pending_approval')),
            ];
        }

        return [
            'client_name' => $client_name,
            'summary' => sanitize_text_field((string) ($data['summary'] ?? 'Parsed task list')),
            'tasks' => $tasks,
        ];
    }
}
