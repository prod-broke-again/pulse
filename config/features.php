<?php

declare(strict_types=1);

return [
    'ai_department_assignment' => (bool) env('FEATURE_AI_DEPARTMENT_ASSIGNMENT', false),
    'ai_department_confidence_threshold' => (float) env('FEATURE_AI_DEPT_CONFIDENCE', 0.7),
];
