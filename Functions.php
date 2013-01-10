<?php

function get_millitime(){
    return round(time(true)*1000);
}

function send_frames($socket,$frames){
    $last=array_pop($frames);
    foreach ($frames as $frame){
        $socket->send($frame,ZMQ::MODE_SNDMORE);
    }
    $socket->send($last);
}

function receive_frames($socket){
    $frames=array();
    do{
        $frames[]=$socket->recv();
    }while ($socket->getsockopt(ZMQ::SOCKOPT_RCVMORE));
    return $frames;
}

/**
 * 解开消息 list(envelope,message)
 * @param array $frames  消息的第一帧为信封，第二帧为空白帧，第二帧以后为发送的消息
 * @example $frames Array
(
    [0] =>���R�C��J�
�
    [1] =>
    [2] => hello
    [3] => world
)

 */
function envelope_unwarp(array $frames){
    $i=array_search('', $frames,TRUE);
    if($i===NULL||$i===FALSE){
        return array(array(),$frames);
    }
    return array(array_slice($frames,0,$i),array_slice($frames, $i+1));
}

/**
 * 包裹消息
 * @param array $envelope
 * @param array $message
 */
function envelope_warp(array $envelope,array $message){
    return array_merge($envelope,array(''),$message);
}