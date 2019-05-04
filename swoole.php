<?php namespace srv; // vim: se fdm=marker:
class test{
  function ok(){
    return 'sdlfjlsdfjldlsflkdslfk';
  }
}

/**
 * @description cli模式下，悄咪咪的启动swoole，并转换$_GET等系统变量，并提供路由功能
 * @todo 如何确定HTTP谓词和路由之间多对多的关系？
 * @fixme 任意cli启动一个entry文件，都能正确识别其他entry实体文件的路由吗？
 */
final class swoole{

  /**
   * @fixme 强制cli使用swoole，将与hook冲突？
   * @param $host 127.0.0.1和::1表示本机地址，0.0.0.0和::表示全部地址
   * @param $port 如果sock_type为UnixSocket_Stream/Dgram，则忽略；小于1024需要root
   * @param $mode = SWOOLE_PROCESS多进程模式，或者SWOOLE_BASE
   * @param $sock_type TCP，UDP，TCP6，UDP6，UnixSocket_Stream/Dgram 6种
   */
  final function __construct(string $host, int $port){
    /**/
    if(PHP_SAPI === 'cli')

      try{

        $srv = new \Swoole\Http\Server($host, $port);

        $srv->on('request', function(\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($srv) {

          $_SERVER['REQUEST_URI'] = $request->server['request_uri'];
          $_SERVER['REQUEST_METHOD'] = $request->server['request_method'];

          //$response->status(http_response_code());//FIXME swoole比原生php严格，不许自定义

          //$response->header();//TODO 转换
          //$response->cookie();//TODO 转换
          //$response->redirect();//TODO 转换

          //$response->sendfile('xxx');//FIXME 如果不是php，则直接发送

          //TODO 路由，将各自处理分布在各个实体文件里，便于模块化管理
          //FIXME 不要硬编码，按实际文件系统处理
          //FIXME 如果引用的文件来自api类，则应该自动统计http响应头
          switch($_SERVER['REQUEST_URI']){
            case '/a':
            case '/a/':


              //FIXME 预感到cli模式可能与各种rpc冲突，无法准确获得POST的php://input净荷
              ob_start();
              @(new \Yar_Server(new test))->handle();
              //var_dump(ob_get_contents());
              $content = ob_get_contents();//FIXME 抛弃？
              ob_clean();
              ob_end_flush();
              @$response->end($content);
              break;

              //include './a.php';
              //TODO 实现简单的内容协商，隐式后缀，多重后缀
              //TODO 转换其中所有对响应头的改动
              //TODO 提前转换变量，供实际入口文件内部判断，如：verb, php://input

              ob_start();
              $o = include('./a.php');
              $php = (string)$o;
              $arr = $o->headers_list??'sdlkfjdlskflkds';
              $content = ob_get_contents();//FIXME 抛弃？
              ob_clean();

              $response->write($php);
              $response->write(json_encode($o));
              break;
            case '/b':
              include './b.php';
              //TODO 转换其中所有对响应头的改动
              $response->write(include('./b.php'));
              break;
            case '/':
            default:
              $response->write('default');
              $response->write('rawContent: '.$request->rawContent());
              break;
          }


          /**/
          //var_dump($request->server['request_uri']);
          //var_dump($request->server['request_method']);
          //var_dump($request->server);

          //var_dump('content '.$content);
          /**/


          //TODO 监测ob_contents异动，及时转换成end输出
          //$response->header('Content-Type', 'text/html');
          //$response->end(static::class);
        });

        $srv->on('start',[$this,'start']);
        $srv->on('close',[$this,'close']);
        $srv->on('finish',[$this,'finish']);
        $srv->on('connect',[$this,'connect']);
        $srv->on('receive',[$this,'receive']);
        $srv->on('workerStart',[$this,'workerStart']);

        $srv->on('shutdown',[$this,'__destruct']);

        //var_dump(swoole_get_local_ip());

        //TODO start之前，welcome开场白致辞，console高亮

        //FIXME 但是start之后，后续代码暂时无法执行了
        //FIXME 服务器关闭后，start返回false，并继续向下执行。所以die
        //FIXME 启动失败也会返回false
        $srv->start() || die;

      }catch(\Swoole\Exception $e){
        //TODO console高亮
        //echo 'ERR: ', $e->getMessage(), PHP_EOL;
        //var_dump($e);
      }

    return '如果swoole服务器关闭，则返回，供__toString打印到console';

    /**/
  }

  function __destruct(){
    //echo __METHOD__, PHP_EOL;
  }



  //不同的进程内分别各自并发触发，且时间顺序不确定
  function start(\Swoole\Server $server){
    //var_dump($server);
    //echo __METHOD__, PHP_EOL;
  }

  function workerStart(\Swoole\Server $server, int $workerId){
    //echo __METHOD__, $workerId, PHP_EOL;
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
    //echo __METHOD__, $fd, $reactorId, PHP_EOL;
  }

  function receive(\Swoole\Server $server, int $fd, int $reactorId, string $data){
    echo __METHOD__, $fd, $reactorId, $data, PHP_EOL;
  }

  function close(\Swoole\Server $server, int $fd, int $reactorId){
    //echo __METHOD__, $fd, $reactorId, PHP_EOL;
  }

  //abstract function packet();

  //abstract function bufferFull();

  //abstract function bufferEmpty();

  //abstract function task();//仅限task进程中触发

  function finish(\Swoole\Server $server, int $taskId, string $data){//仅限worker进程中触发
    echo __METHOD__, $taskId, $data, PHP_EOL;
  }

  //abstract function pipeMessage();

  //abstract function workerError();

  //abstract function managerStop();

}


/**
 * 兼容cli模式的swoole
 */
function header(string $string, bool $replace=true, int $code=null):void{
//var_dump(headers_sent(),array_column(debug_backtrace(),'object'));

  if(!headers_sent() && PHP_SAPI !== 'cli')
    \header($string, $replace, $code);

  if(PHP_SAPI==='cli' && stripos($string,'HTTP/')!==0 && $objects=array_column(debug_backtrace(),'object'))
    (function(string $key, string $value, bool $replace){
      if($replace || empty($this->headers_list[$key]))
        $this->headers_list[$key] = $value;
      else
        $this->headers_list[$key] .= ", $value";
    })->call(
      end($objects),//FIXME reset第一个？next第二个？end最后一个？
      strtolower(strstr($string,':',true)),
      trim(substr($string, strpos($string, ':')+1)),
      $replace
    );
}

function headers_sent():bool{
  return PHP_SAPI==='cli'?false:\headers_sent();
}


function headers_list():array{
  if(PHP_SAPI==='cli'){
    //var_dump(array_column(debug_backtrace(),'object'));
    return array_values(array_map(function($v,$k){
      return "$k: $v";
    },array_column(debug_backtrace(),'object')[-1]->headers_list??[]));
  }else return \headers_list();
}


function header_remove(string $name=null):void{
  headers_sent() or \header_remove($name);
  $objects=array_column(debug_backtrace(),'object');
  if(PHP_SAPI==='cli' && $obj=end($objects))
    if($name) unset($obj->headers_list[$name]);
    else unset($obj->headers_list);
}
