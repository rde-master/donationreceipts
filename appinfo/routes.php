<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'stats#index', 'url' => '/api/stats', 'verb' => 'GET'],

        ['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
        ['name' => 'settings#setFolder', 'url' => '/api/settings/folders/{variant}', 'verb' => 'PUT', 'requirements' => ['variant' => '(unsigned|signed|logos)']],
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

        ['name' => 'mail#preview', 'url' => '/api/receipts/{id}/mail', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'mail#send', 'url' => '/api/receipts/{id}/mail', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'mail#test', 'url' => '/api/mail/test', 'verb' => 'POST'],

        ['name' => 'sponsor#index', 'url' => '/api/sponsors', 'verb' => 'GET'],
        ['name' => 'sponsor#create', 'url' => '/api/sponsors', 'verb' => 'POST'],
        ['name' => 'sponsor#import', 'url' => '/api/sponsors/import', 'verb' => 'POST'],
        ['name' => 'sponsor#show', 'url' => '/api/sponsors/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'sponsor#update', 'url' => '/api/sponsors/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'sponsor#destroy', 'url' => '/api/sponsors/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],

        ['name' => 'letterTemplate#index', 'url' => '/api/letter-templates', 'verb' => 'GET'],
        ['name' => 'letterTemplate#create', 'url' => '/api/letter-templates', 'verb' => 'POST'],
        ['name' => 'letterTemplate#update', 'url' => '/api/letter-templates/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'letterTemplate#destroy', 'url' => '/api/letter-templates/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],

        ['name' => 'campaign#index', 'url' => '/api/campaigns', 'verb' => 'GET'],
        ['name' => 'campaign#create', 'url' => '/api/campaigns', 'verb' => 'POST'],
        ['name' => 'campaign#show', 'url' => '/api/campaigns/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
        ['name' => 'campaign#update', 'url' => '/api/campaigns/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
        ['name' => 'campaign#destroy', 'url' => '/api/campaigns/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
        ['name' => 'campaign#preview', 'url' => '/api/campaigns/{id}/preview', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'campaign#send', 'url' => '/api/campaigns/{id}/send', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],

        ['name' => 'inquiry#open', 'url' => '/api/inquiries/open', 'verb' => 'GET'],
        ['name' => 'inquiry#receive', 'url' => '/api/inquiries/{id}/receive', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
        ['name' => 'inquiry#logo', 'url' => '/api/inquiries/{id}/logo', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],

        ['name' => 'adminSettings#setGroup', 'url' => '/api/admin/group', 'verb' => 'PUT'],

        // public response form for sponsors (no login)
        ['name' => 'response#show', 'url' => '/r/{token}', 'verb' => 'GET', 'requirements' => ['token' => '[A-Za-z0-9]{32}']],
        ['name' => 'response#submit', 'url' => '/r/{token}', 'verb' => 'POST', 'requirements' => ['token' => '[A-Za-z0-9]{32}']],
    ],
];
