<?php namespace srv; // vim: se fdm=marker:

/**
 * @description cli模式下，悄咪咪的启动swoole，并转换$_GET等系统变量，并提供路由功能
 * @todo 如何确定HTTP谓词和路由之间多对多的关系？
 */
class swoole extends api{

  /**
   * @fixme 强制cli使用swoole，将与hook冲突？
   * @param $host 127.0.0.1和::1表示本机地址，0.0.0.0和::表示全部地址
   * @param $port 如果sock_type为UnixSocket_Stream/Dgram，则忽略；小于1024需要root
   * @param $mode = SWOOLE_PROCESS多进程模式，或者SWOOLE_BASE
   * @param $sock_type TCP，UDP，TCP6，UDP6，UnixSocket_Stream/Dgram 6种
   */
  final function __construct(string $host, int $port){
    /**/
    if(PHP_SAPI === 'cli'){
      //TODO 启动swoole.start()
      //FIXME 但是start之后，后续代码应该无法执行了
      $srv = new \Swoole\Http\Server($host, $port);

      $srv->on('request', function($request, $response){
        $response->end(static::class);
      });

      $srv->on('start',[$this,'start']);
      $srv->on('close',[$this,'close']);
      $srv->on('finish',[$this,'finish']);
      $srv->on('connect',[$this,'connect']);
      $srv->on('receive',[$this,'receive']);
      $srv->on('workerStart',[$this,'workerStart']);

      $srv->on('shutdown',[$this,'__destruct']);

      var_dump(swoole_get_local_ip());

      //TODO start之前，welcome开场白致辞

      try{
        $srv->start();
      }catch(\Swoole\Exception $e){
        var_dump($e);
      }
    }
    /**/
  }

  function __destruct(){
    var_dump($this);
  }



  //不同的进程内分别各自并发触发，且时间顺序不确定
  function start(\Swoole\Server $server){
    //var_dump($server);
    echo 'onStart:', PHP_EOL;
  }

  function workerStart(\Swoole\Server $server, int $workerId){
    echo 'onWorkerStart:', PHP_EOL;
  }

  //abstract function managerStart();

  //abstract function shutdown();

  //abstract function workerStop();

  //abstract function workerExit();

  /**
   * Worker进程中触发
   * @param $server
   */
  function connect(\Swoole\Server $server, int $fd, int $reactorId){
    echo 'onConnect:', $fd, $reactorId, PHP_EOL;
  }

  function receive(\Swoole\Server $server, int $fd, int $reactorId, string $data){
    echo 'onReceive:', $fd, $reactorId, $data, PHP_EOL;
  }

  function close(\Swoole\Server $server, int $fd, int $reactorId){
    echo 'onClose:', $fd, $reactorId, PHP_EOL;
  }

  //abstract function packet();

  //abstract function bufferFull();

  //abstract function bufferEmpty();

  //abstract function task();//仅限task进程中触发

  function finish(\Swoole\Server $server, int $taskId, string $data){//仅限worker进程中触发
    echo 'onFinish:', $taskId, $data, PHP_EOL;
  }

  //abstract function pipeMessage();

  //abstract function workerError();

  //abstract function managerStop();


  final function __toString():string{
    return '此时失去了存在的意义';
  }

}
