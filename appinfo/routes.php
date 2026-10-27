<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
        ['name' => 'settings#setFolder', 'url' => '/api/settings/folders/{variant}', 'verb' => 'PUT', 'requirements' => ['variant' => '(unsigned|signed)']],
        ['name' => 'image#show', 'url' => '/api/images/{kind}', 'verb' => 'GET', 'requirements' => ['kind' => '(signature|stamp)']],
        ['name' => 'image#upload', 'url' => '/api/images/{kind}', 'verb' => 'POST', 'requirements' => ['kind' => '(signature|stamp)']],
        ['name' => 'image#destroy', 'url' => '/api/images/{kind}', 'verb' => 'DELETE', 'requirements' => ['kind' => '(signature|stamp)']],

        ['name' => 'donor#index', 'url' => '/api/donors', 'verb' => 'GET'],
        ['name' => 'donor#create', 'url' => '/api/donors', 'verb' => 'POST'],
        ['name' => 'donor#show', 'url' => '/api/donors/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'donor#update', 'url' => '/api/donors/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'donor#destroy', 'url' => '/api/donors/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],

        ['name' => 'receipt#index', 'url' => '/api/receipts', 'verb' => 'GET'],
        ['name' => 'receipt#create', 'url' => '/api/donors/{donorId}/receipts', 'verb' => 'POST', 'requirements' => ['donorId' => '\d+']],
        ['name' => 'receipt#show', 'url' => '/api/receipts/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'receipt#update', 'url' => '/api/receipts/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'receipt#file', 'url' => '/api/receipts/{id}/file/{variant}', 'verb' => 'GET', 'requirements' => ['id' => '\d+', 'variant' => '(unsigned|signed)']],

        ['name' => 'adminSettings#setGroup', 'url' => '/api/admin/group', 'verb' => 'PUT'],
    ],
];
