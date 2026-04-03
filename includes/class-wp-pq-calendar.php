<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Calendar
{
    public static function create_meeting(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $task_id = (int) $request->get_param('id');
        $task = WP_PQ_API::get_task_row($task_id);

        if (! $task || ! WP_PQ_API::can_access_task($task, get_current_user_id())) {
            return new WP_REST_Response(['message' => 'Forbidden.'], 403);
        }

        $starts_at = WP_PQ_API::sanitize_datetime($request->get_param('starts_at'));
        $ends_at = WP_PQ_API::sanitize_datetime($request->get_param('ends_at'));
        $event_id = sanitize_text_field((string) $request->get_param('event_id'));
        $meeting_url = esc_url_raw((string) $request->get_param('meeting_url'));

        if ($event_id === '' && $starts_at && $ends_at) {
            try {
                $google_event = self::create_google_calendar_event($task, $starts_at, $ends_at);
            } catch (\Throwable $e) {
                error_log('PQ Meeting Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                return new WP_REST_Response(['message' => 'Google Calendar error: ' . $e->getMessage()], 500);
            }
            if (is_wp_error($google_event)) {
                error_log('PQ Meeting WP_Error: ' . $google_event->get_error_message());
                return new WP_REST_Response(['message' => $google_event->get_error_message()], 500);
            }
            $event_id = sanitize_text_field((string) ($google_event['id'] ?? ''));
            $meeting_url = esc_url_raw((string) ($google_event['hangoutLink'] ?? ''));
        }

        $table = $wpdb->prefix . 'pq_task_meetings';
        $wpdb->insert($table, [
            'task_id' => $task_id,
            'provider' => 'google',
            'event_id' => $event_id,
            'meeting_url' => $meeting_url,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'sync_direction' => 'two_way',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);

        $meeting = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE task_id = %d ORDER BY id DESC LIMIT 1", $task_id),
            ARRAY_A
        );

        return new WP_REST_Response([
            'ok' => true,
            'event_id' => $event_id,
            'meeting_url' => $meeting_url,
            'meeting' => $meeting,
            'task' => WP_PQ_API::get_enriched_task($task_id),
        ], 201);
    }

    public static function calendar_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_json_params();
        do_action('wp_pq_calendar_webhook_received', $payload);

        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function get_calendar_events(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $tasks_table = $wpdb->prefix . 'pq_tasks';
        $meetings_table = $wpdb->prefix . 'pq_task_meetings';
        $user_id = get_current_user_id();

        $tasks = WP_PQ_API::get_visible_tasks_for_request($request, $user_id, true);

        self::backfill_task_calendar_events($tasks);

        $events = [];
        $visible_task_ids = [];

        foreach ($tasks as $task) {
            $visible_task_ids[] = (int) $task['id'];
            $dt = $task['requested_deadline'] ?: $task['due_at'];
            if (! $dt) {
                continue;
            }

            $events[] = [
                'id' => 'task_' . (int) $task['id'],
                'title' => '[Task] ' . (string) $task['title'],
                'start' => gmdate('c', strtotime((string) $dt . ' UTC')),
                'allDay' => false,
                'backgroundColor' => '#be123c',
                'borderColor' => '#be123c',
                'extendedProps' => [
                    'source' => 'task',
                    'taskId' => (int) $task['id'],
                    'status' => (string) $task['status'],
                    'priority' => (string) $task['priority'],
                    'description' => (string) $task['description'],
                    'needsMeeting' => ! empty($task['needs_meeting']),
                    'requestedDeadline' => (string) $task['requested_deadline'],
                    'dueAt' => (string) $task['due_at'],
                ],
            ];
        }

        if (! empty($visible_task_ids)) {
            $ids_in = implode(',', array_map('intval', $visible_task_ids));
            $meetings = $wpdb->get_results("SELECT * FROM {$meetings_table} WHERE task_id IN ({$ids_in}) ORDER BY id DESC", ARRAY_A);

            foreach ($meetings as $meeting) {
                if (! empty($meeting['starts_at'])) {
                    $events[] = [
                        'id' => 'meeting_' . (int) $meeting['id'],
                        'title' => '[Meet] Task #' . (int) $meeting['task_id'],
                        'start' => gmdate('c', strtotime((string) $meeting['starts_at'] . ' UTC')),
                        'end' => ! empty($meeting['ends_at']) ? gmdate('c', strtotime((string) $meeting['ends_at'] . ' UTC')) : null,
                        'allDay' => false,
                        'backgroundColor' => '#1d4ed8',
                        'borderColor' => '#1d4ed8',
                        'url' => ! empty($meeting['meeting_url']) ? $meeting['meeting_url'] : null,
                        'extendedProps' => [
                            'source' => 'meeting',
                            'taskId' => (int) $meeting['task_id'],
                            'eventId' => (string) $meeting['event_id'],
                            'meetingUrl' => (string) $meeting['meeting_url'],
                        ],
                    ];
                }
            }
        }

        $google_events = self::fetch_google_calendar_events();
        if (is_array($google_events)) {
            foreach ($google_events as $google_event) {
                $events[] = $google_event;
            }
        }

        return new WP_REST_Response(['events' => $events], 200);
    }

    private static function create_google_calendar_event(array $task, string $starts_at, string $ends_at)
    {
        // Use the acting user's token — the person scheduling the meeting.
        $token = WP_PQ_Google_Auth::get_google_access_token(get_current_user_id());
        if ($token === '') {
            return new WP_Error('google_not_connected', 'Google is not connected. Complete OAuth first.');
        }

        $description = (string) ($task['description'] ?? '');
        $summary = 'Priority Task #' . (int) $task['id'] . ': ' . (string) ($task['title'] ?? 'Meeting');
        $request_id = 'pq_' . wp_generate_uuid4();
        $attendees = self::meeting_attendees($task);
        $body = [
            'summary' => $summary,
            'description' => $description,
            'start' => [
                'dateTime' => gmdate('c', strtotime($starts_at . ' UTC')),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => gmdate('c', strtotime($ends_at . ' UTC')),
                'timeZone' => 'UTC',
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => $request_id,
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ],
            'guestsCanModify' => false,
            'guestsCanInviteOthers' => true,
        ];
        if (! empty($attendees)) {
            $body['attendees'] = $attendees;
        }

        $resp = wp_remote_post('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1&sendUpdates=all', [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($resp)) {
            return new WP_Error('google_event_error', $resp->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $data = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($data) || empty($data['id'])) {
            return new WP_Error('google_event_error', 'Failed to create Google Calendar event.');
        }

        return $data;
    }

    private static function meeting_attendees(array $task): array
    {
        $emails = [];

        $submitter = isset($task['submitter_id']) ? WP_PQ_API::get_cached_user((int) $task['submitter_id']) : null;
        if ($submitter && is_email($submitter->user_email)) {
            $emails[] = (string) $submitter->user_email;
        }

        $emails = array_values(array_unique(array_filter($emails, 'is_email')));

        return array_map(static fn($email) => ['email' => $email], $emails);
    }

    /**
     * Defer calendar sync to after the HTTP response is sent.
     * This eliminates the 3-20 second Google API delay from user-facing actions.
     */
    public static function sync_task_calendar_event(array $task): void
    {
        WP_PQ_API::defer(static function () use ($task): void {
            try {
                self::sync_task_calendar_event_inner($task);
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('PQ calendar sync failed: ' . $e->getMessage());
                }
            }
        });
    }

    private static function sync_task_calendar_event_inner(array $task): void
    {
        global $wpdb;

        if (empty($task) || empty($task['id'])) {
            return;
        }

        $table = $wpdb->prefix . 'pq_tasks';
        $event_id = (string) ($task['google_event_id'] ?? '');
        $starts_at = (string) ($task['requested_deadline'] ?: $task['due_at'] ?: '');

        if ($starts_at === '') {
            if ($event_id !== '') {
                $del_user_id = (int) ($task['action_owner_id'] ?? 0) ?: get_current_user_id();
                self::delete_google_calendar_event($event_id, $del_user_id);
                $wpdb->update($table, [
                    'google_event_id' => null,
                    'google_event_url' => null,
                    'google_event_synced_at' => current_time('mysql', true),
                    'updated_at' => current_time('mysql', true),
                ], ['id' => (int) $task['id']]);
            }
            return;
        }

        // Use the action owner's token so the event appears on their calendar.
        // Fall back to the current user if no action owner is set.
        $cal_user_id = (int) ($task['action_owner_id'] ?? 0);
        if ($cal_user_id <= 0) {
            $cal_user_id = get_current_user_id();
        }
        $token = WP_PQ_Google_Auth::get_google_access_token($cal_user_id);
        if ($token === '') {
            return;
        }

        $start_ts = strtotime($starts_at . ' UTC');
        if (! $start_ts) {
            return;
        }

        $ends_at = gmdate('Y-m-d H:i:s', $start_ts + HOUR_IN_SECONDS);
        $summary = 'Priority Task #' . (int) $task['id'] . ': ' . WP_PQ_API::task_title($task);
        $description = trim((string) ($task['description'] ?? ''));
        $body = [
            'summary' => $summary,
            'description' => $description,
            'start' => [
                'dateTime' => gmdate('c', $start_ts),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => gmdate('c', strtotime($ends_at . ' UTC')),
                'timeZone' => 'UTC',
            ],
            'extendedProperties' => [
                'private' => [
                    'wp_pq_task_id' => (string) (int) $task['id'],
                ],
            ],
        ];

        $url = $event_id !== ''
            ? 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($event_id) . '?sendUpdates=none'
            : 'https://www.googleapis.com/calendar/v3/calendars/primary/events?sendUpdates=none';
        $method = $event_id !== '' ? 'PUT' : 'POST';

        $resp = wp_remote_request($url, [
            'method' => $method,
            'timeout' => 3,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($resp)) {
            return;
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $data = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($data) || empty($data['id'])) {
            return;
        }

        $wpdb->update($table, [
            'google_event_id' => sanitize_text_field((string) $data['id']),
            'google_event_url' => esc_url_raw((string) ($data['htmlLink'] ?? '')),
            'google_event_synced_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['id' => (int) $task['id']]);
    }

    private static function backfill_task_calendar_events(array $tasks): void
    {
        if (empty($tasks) || WP_PQ_Google_Auth::get_google_access_token() === '') {
            return;
        }

        foreach ($tasks as $task) {
            $starts_at = (string) ($task['requested_deadline'] ?: $task['due_at'] ?: '');
            if ($starts_at === '') {
                continue;
            }

            $event_id = (string) ($task['google_event_id'] ?? '');
            if ($event_id !== '') {
                continue;
            }

            self::sync_task_calendar_event((array) $task);
        }
    }

    public static function delete_google_calendar_event(string $event_id, int $user_id = 0): void
    {
        $token = WP_PQ_Google_Auth::get_google_access_token($user_id ?: get_current_user_id());
        if ($token === '' || $event_id === '') {
            return;
        }

        wp_remote_request('https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($event_id) . '?sendUpdates=none', [
            'method' => 'DELETE',
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
    }

    private static function fetch_google_calendar_events(): array
    {
        $token = WP_PQ_Google_Auth::get_google_access_token(get_current_user_id());
        if ($token === '') {
            return [];
        }

        $time_min = rawurlencode(gmdate('c', strtotime('-30 days')));
        $time_max = rawurlencode(gmdate('c', strtotime('+90 days')));
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events?singleEvents=true&orderBy=startTime&maxResults=100&timeMin=' . $time_min . '&timeMax=' . $time_max;

        $resp = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);
        if (is_wp_error($resp)) {
            return [];
        }

        $status = (int) wp_remote_retrieve_response_code($resp);
        $data = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($status >= 300 || ! is_array($data) || empty($data['items']) || ! is_array($data['items'])) {
            return [];
        }

        $events = [];
        foreach ($data['items'] as $item) {
            if (! empty($item['extendedProperties']['private']['wp_pq_task_id'])) {
                continue;
            }

            $start = '';
            $end = '';
            $all_day = false;

            if (! empty($item['start']['dateTime'])) {
                $start = (string) $item['start']['dateTime'];
                $end = ! empty($item['end']['dateTime']) ? (string) $item['end']['dateTime'] : '';
            } elseif (! empty($item['start']['date'])) {
                $start = (string) $item['start']['date'];
                $end = ! empty($item['end']['date']) ? (string) $item['end']['date'] : '';
                $all_day = true;
            }

            if ($start === '') {
                continue;
            }

            $events[] = [
                'id' => 'gcal_' . sanitize_text_field((string) ($item['id'] ?? wp_generate_uuid4())),
                'title' => '[GCal] ' . sanitize_text_field((string) ($item['summary'] ?? 'Google Event')),
                'start' => $start,
                'end' => $end ?: null,
                'allDay' => $all_day,
                'backgroundColor' => '#0f766e',
                'borderColor' => '#0f766e',
                'url' => ! empty($item['htmlLink']) ? esc_url_raw((string) $item['htmlLink']) : null,
                'extendedProps' => [
                    'source' => 'google',
                ],
            ];
        }

        return $events;
    }
}
