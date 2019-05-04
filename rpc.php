<?php namespace srv; // vim: se fdm=marker:

abstract class rpc extends api{

  final function __toString():string{
    try{
      return $this();
    }catch(\Throwable $t){
      return '';
    }
  }


  /**
   * 收集用户自定义public方法，为了向GET请求提供IDL说明
   * @fixme protected是为了向父类的__debugInfo调用权限
   */
  final protected function method():array{
    $arr = [];
    foreach((new \ReflectionClass($this))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m){
      if($m->class === static::class)
        $arr[$m->name] = $m;
    }
    return $arr;
  }


  /**
   * @description soap需要class.wsdl，grpc需要class.proto，yar需要IDL.html
   */
  abstract function GET():string;

  abstract function POST():void;

}
