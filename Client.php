<?php
require_once dirname(__FILE__).'/Functions.php';
/**
 *
 * @author zhxia
 * 为了方便测试，这里的消息是指简单时用php自身的序列化方法，为了获取更好的性能，可以使用msgpack之类的工具进行消息的打包
 * message sended format: [version,command,[sequence,timestamp,expires],method,params]
 * message received format: [version,command,[sequence,timestamp,status],reply]
 *
 */
class Client {
    const VERSION='10';
    private $socket;
    protected $expires;
    private static $sequence=0;
    private static $sockets=array();
    private static $requests=array();
    public function __construct($context,$endpoint){
        $socket=new ZMQSocket($context,ZMQ::SOCKET_XREQ);
        $socket->setsockopt(ZMQ::SOCKOPT_LINGER, 0);
        $socket->setsockopt(ZMQ::SOCKOPT_HWM, 1000);
        $socket->connect($endpoint);
        $this->socket=$socket;
        self::$sockets[]=$socket;
        $this->expires=1000*1000;
    }

    /**
     * 发送请求
     * @param string $method
     * @param array $params
     * @param callable $callback
     * @param int $expires (ms)
     */
    public function start_request($method,array $params,$callback=NULL,$expires=NULL){
        $sequence=++self::$sequence;
        $timestamp=get_millitime();
        if($expires===NULL){
            $expires=$this->expires;
        }
        //创建请求帧
        $frames=array();
        $frames[]='';
        $frames[]=self::VERSION;
        $frames[]=chr(0x00);
        $frames[]=serialize(array($sequence,$timestamp,$expires));
        $frames[]=$method;
        $frames[]=serialize($params);
        send_frames($this->socket, $frames);
        self::$requests[$sequence]=array($this,$callback); //保存请求时的回调，便于在结果返回时调用
        return $sequence;
    }

    /**
     * 等待响应结果
     * @param int $timeout(default 100ms)
     */
    public function wait_for_reply($timeout = 100) {
        $poll = new ZMQPoll ();
        foreach ( self::$sockets as $socket ) {
            $poll->add ( $socket, ZMQ::POLL_IN );
        }
        $readable = $writeable = array ();
        while ( count ( self::$requests ) > 0 ) {
            $events = $poll->poll ( $readable, $writeable, $timeout );
            if (empty ( $events )){
                break;
            }

            foreach ( $readable as $socket ) {
                self::process_reply ( $socket );
            }
        }
    }

    /**
     * 处理响应结果
     * @param zmq_socket $socket
     */
    protected static function process_reply($socket){
        $frames=receive_frames($socket);
        list ( $envelope, $message ) = envelope_unwarp ( $frames );
        $reply=unserialize($message[3]);
        list($sequence,$timstamp,$status)=unserialize($message[2]);
        list($me,$callback)=self::$requests[$sequence];
        if($callback){
            call_user_func_array($callback,array($reply));
        }
        unset(self::$requests[$sequence]);
    }
}

?>