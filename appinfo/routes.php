<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],

        ['name' => 'donor#index', 'url' => '/api/donors', 'verb' => 'GET'],
        ['name' => 'donor#create', 'url' => '/api/donors', 'verb' => 'POST'],
        ['name' => 'donor#show', 'url' => '/api/donors/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'donor#update', 'url' => '/api/donors/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'donor#destroy', 'url' => '/api/donors/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],

        ['name' => 'receipt#index', 'url' => '/api/receipts', 'verb' => 'GET'],
        ['name' => 'receipt#create', 'url' => '/api/donors/{donorId}/receipts', 'verb' => 'POST', 'requirements' => ['donorId' => '\d+']],
        ['name' => 'receipt#show', 'url' => '/api/receipts/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'receipt#update', 'url' => '/api/receipts/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'receipt#pdf', 'url' => '/api/receipts/{id}/pdf', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],

        ['name' => 'adminSettings#setGroup', 'url' => '/api/admin/group', 'verb' => 'PUT'],
    ],
];
