<?php

ini_set('max_execution_time', -1);
require_once('vendor/autoload.php');

include './DownloadCollection.php';

$options = [
    'shutterstockUsername' => 'john.doe@example.com',
    'shutterstockPassword' => 'secret',
    'collectionId' => 123456,
    'subscriptionText' => '365-day Images On Demand, with 5 Standard License Downloads'
];

$dc = new DownloadCollection($options);
$dc->go();



