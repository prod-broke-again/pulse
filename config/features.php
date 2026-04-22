<?php

declare(strict_types=1);

return [
    'ai_department_assignment' => (bool) env('FEATURE_AI_DEPARTMENT_ASSIGNMENT', false),
    'ai_department_confidence_threshold' => (float) env('FEATURE_AI_DEPT_CONFIDENCE', 0.7),
    'ai_client_autoreply' => (bool) env('FEATURE_AI_CLIENT_AUTOREPLY', true),
    'ai_client_autoreply_min_conf' => (float) env('FEATURE_AI_CLIENT_AUTOREPLY_MIN_CONF', 0.85),
    'ai_widget_max_auto_replies' => (int) env('FEATURE_AI_WIDGET_MAX_AUTO_REPLIES', 3),
    'ai_idle_close_hours' => (int) env('FEATURE_AI_IDLE_CLOSE_HOURS', 24),
    'ai_idle_close_resolved_state' => (bool) env('FEATURE_AI_IDLE_CLOSE_RESOLVED', true),
    'admin_ai_prompt' => (string) env('ADMIN_AI_PROMPT', ''),
    'admin_ai_rules' => (string) env('ADMIN_AI_RULES', ''),
];
