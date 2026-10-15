<?php

return [
    'paths' => ['app'],
    'metadata_paths' => ['app'],
    'exclude' => ['vendor', 'storage', 'bootstrap/cache'],
    'rules' => [
        'N1' => true,
        'N2' => true,
        'N3' => true,
        'N4' => true,
    ],
    'minimum_severity' => 'low',
    'resource_models' => [],
    'additional_rules' => [],
];
