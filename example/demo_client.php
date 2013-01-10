<?php
require_once '../Client.php';
$context=new ZMQContext();
$client=new Client($context,'ipc:///tmp/front.ipc');
$client->start_request('get_from_database',array('select * from words where id>1000 limit 10'),function ($reply){
    print_r($reply);
});

$client->start_request('get_from_database',array('select * from user'),function ($reply){
    print_r($reply);
});

$client->start_request('get_from_database',array('select count(*) from words'),function ($reply){
    print_r($reply);
});
$client->wait_for_reply(10);
