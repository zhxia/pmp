<?php
require_once dirname ( __FILE__ ) . '/Functions.php';
/**
 * 该类是提供消息的接收和分配的服务，可以用来解决一些并发问题，通过消息的中包含的ID进行分组，相同ID被分配到相同的进程处理。从而避免并发
 * @author zhxia
 *
 */
class Proxy {
    private $worker_num = 1;
    private $socket_client;
    private $socket_workers = array ();
    private $interval;
    private $interrupted;
    private $workers = array ();
    public function __construct($context, $frontend, array $backends) {
        $socket = new ZMQSocket ( $context, ZMQ::SOCKET_XREP);
        $socket->setsockopt ( ZMQ::SOCKOPT_LINGER, 0 );
        $socket->bind ( $frontend ); //绑定前端，为客户端的请求服务
        $this->socket_client = $socket;
        //创建多个后端服务
        foreach ( $backends as $backend ) {
            $socket = new ZMQSocket ($context, ZMQ::SOCKET_XREQ);
            $socket->setsockopt ( ZMQ::SOCKOPT_LINGER, 0 );
            $socket->bind ( $backend );
            $this->socket_workers [] = $socket;
        }
        $this->interval = 1000 * 1000;
        $this->interrupted = False;
        $this->worker_num = count ( $backends );
    }

    public function run() {
        echo 'proxy is running...' . PHP_EOL;
        while ( ! $this->interrupted ) {
            $poll = new ZMQPoll ();
            $poll->add ( $this->socket_client, ZMQ::POLL_IN );
            foreach ( $this->socket_workers as $socket_worker ) {
                $poll->add ( $socket_worker, ZMQ::POLL_IN );
            }
            $readable = $writeable = array ();
            $events = $poll->poll ( $readable, $writeable, $this->interval );
            if ($events == 0)
                continue;
            foreach ( $readable as $socket ) {
                if ($socket === $this->socket_client) {
                    $this->process_client ();
                } elseif (in_array ( $socket, $this->socket_workers, TRUE )) {
                    $this->process_worker ( $socket );
                }
            }
        }
    }

    public function process_client() {
        $frames = receive_frames ( $this->socket_client );
        list ( $envelope, $message ) = envelope_unwarp ( $frames );
        /*@todo 获取对消息进行分区的值,从而获取指定的客户端(具有相同特征的消息被同一个worker处理)
         这里是随机取worker进行消息的分发，也可以通过消息中的指定信息进行消息的分发 */
        $key = array_rand ( $this->socket_workers );
//         syslog ( LOG_INFO, 'select worker:' . $key );
        $socket_worker = $this->socket_workers [$key];
        array_push($message, $key);
        $frames=envelope_warp($envelope, $message);
        send_frames ( $socket_worker, $frames );
    }

    public function process_worker($socket_worker) {
        $frames = receive_frames ( $socket_worker );
        list ( $envelope, $message ) = envelope_unwarp ( $frames );
        $command = ord ( $message [1] );
        if ($command == 0x00) { //获取处理完的结果，并返回
            send_frames ( $this->socket_client, $frames );
        }
    }

}

?>