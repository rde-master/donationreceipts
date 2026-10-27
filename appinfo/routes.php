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

        ['name' => 'adminSettings#setGroup', 'url' => '/api/admin/group', 'verb' => 'PUT'],
    ],
];
