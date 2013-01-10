<?php
require_once '../Worker.php';
require_once dirname(__FILE__).'/Model.php';
$context=new ZMQContext();
$endpoint=$argv[1];
syslog(LOG_INFO, 'endpoint:'.$endpoint);
$worker=new Worker($context, $endpoint);
$model=new Model();
$worker->set_delegate($model);
$worker->run();