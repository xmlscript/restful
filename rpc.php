<?php namespace srv; // vim: se fdm=marker:

abstract class rpc extends api{


  /**
   * @fixme rpc仅在swoole模式下请求GET或POST，直接输出响应，无需返回
   * @todo 如何兼容rest，通常die掉了
   * @todo 能否启用__call转换抛出异常？rest时返回501错误
   */
  final function __invoke():string{

    //FIXME 大小写？
    if(in_array($method=$_SERVER['REQUEST_METHOD']??null,array_keys($this->method()),true))
      //TODO 执行之前，确保向swoole注入$_GET
      return static::$method();
    else
      throw new \BadMethodCallException('Not Implemented',501);

  }

  /**
   * 收集用户自定义public方法，为了向GET请求提供IDL说明
   * @fixme protected是为了向父类的__debugInfo调用权限
   */
  final protected function method():array{
    $arr = [];
    foreach((new \ReflectionClass($this))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m){
      if(strpos($m->name,'__')!==0)
        if($m->class===self::class){
          if(ctype_upper($m->name))
            $arr[$m->name] = $m;
        }
        elseif(ctype_print($m->name))
          $arr[strtoupper($m->name)] = $m;
    }
    return $arr;
  }


  /**
   * @description soap需要class.wsdl，grpc需要class.proto，yar需要IDL.html
   */
  abstract function GET():string;

  abstract function POST():void;


  final function __toString():string{
    try{
      return $this();
    }catch(\Throwable $t){
      return '';
    }
  }

}
