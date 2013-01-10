zps
===

php multi processer

基于ZMQ的PHP多进程处理框架，可以用于将页面的串行请求改成并行处理，从而可有效用于减少请求的处理时间。
在进行后端的任务分发时，可以自定义对消息进行路由，指定特定的worker来处理消息，解决一些并发的问题

XREP=====================XREP--code--XREQ=======================XREP(workers)

请求时的消息格式：

 message sended format: [version,command,[sequence,timestamp,expires],method,params]

请求结果返回的消息格式：
 message received format: [version,command,[sequence,timestamp,status],reply]
