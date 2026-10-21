<?php

return [
    // UI-only fixtures. This is not the isolated operational demo from DEMO-01.
    'ui_preview_enabled' => (bool) env('HRIS_UI_PREVIEW_ENABLED', false),
    'demo_features_enabled' => (bool) env('DEMO_FEATURES_ENABLED', false),
    'demo_scenario_id' => env('DEMO_SCENARIO_ID'),
    'demo_operator_token' => env('DEMO_OPERATOR_TOKEN'),
];
