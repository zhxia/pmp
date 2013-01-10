<?php
require_once '../Proxy.php';

function fork_and_exec($cmd, $args=array()) {
    $pid = pcntl_fork();
    if ($pid > 0) {
        return $pid;
    } else if ($pid == 0) {
        pcntl_exec($cmd, $args);
        exit(0);
    } else {
        // TODO:
        die('could not fork');
    }
}

$context=new ZMQContext();
$frontend='ipc:///tmp/front.ipc';
$backends=array('ipc:///tmp/back1.ipc','ipc:///tmp/back2.ipc','ipc:///tmp/back3.ipc');
$proxy=new Proxy($context, $frontend, $backends);
$pids=array();
$worker=dirname(__FILE__).'/demo_worker.php';
foreach ($backends as $endpoint){
    $pids[]=fork_and_exec('/usr/bin/env',array('php',$worker,$endpoint));
}
/*
register_shutdown_function(function($pids){
    syslog(LOG_INFO,'kill subprocesses!');
    foreach ($pids as $pid){
        posix_kill($pid,SIGTERM);
    }
}); */

$proxy->run();
