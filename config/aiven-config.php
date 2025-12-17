<?php
// Aiven MySQL Configuration for Render deployment
return [
    'host' => 'mysql-3e1ce2-pucit-337f.f.aivencloud.com',
    'port' => 17642,
    'database' => 'defaultdb',
    'username' => 'avnadmin',
    'password' => 'AVNS_VfCo0APBMLZpWRssU4Y',
    'charset' => 'utf8mb4',
    'ssl' => [
        'ca' => '/etc/ssl/certs/ca-certificates.crt', // Default cert path
        'verify_cert' => true
    ]
];
