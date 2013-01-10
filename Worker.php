<?php
require_once dirname(__FILE__).'/Functions.php';
/**
 *
 * @author zhxia
 *
 */
class Worker {
    const VERSION='10';
    private $socket;
    private $interval;
    private $interrupted;
    private $delegate;
    public function __construct($context,$endpoint){
        $socket=new ZMQSocket($context,ZMQ::SOCKET_XREP);
        $socket->setsockopt(ZMQ::SOCKOPT_LINGER,0);
        $socket->setsockopt(ZMQ::SOCKOPT_IDENTITY,strval(posix_getpid())); //设置一个ID，连接断了之后重新连接上，从上次的位置开始接收消息
        $socket->connect($endpoint);
        $this->socket=$socket;
        $this->interrupted=False;
        $this->interval=1000*1000;
    }

    public function run(){
        $poll=new ZMQPoll();
        $poll->add($this->socket,ZMQ::POLL_IN);
        while (!$this->interrupted){
            $readable=$writeable=array();
            $events=$poll->poll($readable,$writeable,$this->interval);
            if(posix_getppid()==1) break; //防止直接启动worker,必须由父进程fork
            if($events){
                $this->process();
            }
        }
    }

    protected function process(){
        //接收service分配的消息
        $frames=receive_frames($this->socket);
        list($envelope,$message)=envelope_unwarp($frames);
        $command=ord($message[1]);
        if($command==0x00){
            $staus=200;
            $reply=NULL;
            $method=$message[3];
            $params=unserialize($message[4]);
            list($sequence,$timestamp,$expires)=unserialize($message[2]);
            if(!method_exists($this->delegate,$method)){
                $staus=404;
            }
            else{
                $reply=call_user_func_array(array($this->delegate,$method), $params);
            }

            //返回消息给proxy,format:version,command,[sequence,timestamp,status],reply
            $frames=envelope_warp($envelope,array(self::VERSION,chr($command),serialize(array($sequence,0,$staus)),serialize($reply)));
            send_frames($this->socket,$frames);
        }
    }

    public function set_delegate($object){
        $this->delegate=$object;
    }

}

?>