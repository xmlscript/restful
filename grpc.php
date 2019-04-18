<?php namespace srv; // vim: se fdm=marker:

class grpc extends rpc{

  final function POST():void{

    //$soap = new \SoapServer($this->wsdl(),[
    $soap = new \SoapServer(null,[
      'uri' => 'xx', //FIXME nonWSDL模式必须有uri，但是可以随意设置，哪怕empty字符串
    ]);

    $soap->setClass(static::class);
    $soap->handle(); //调用private将产生三条log

    die;//FIXME 后续的请求头控制一律忽略掉了

  }

}
