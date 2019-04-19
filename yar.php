<?php namespace srv; // vim: se fdm=marker:

class yar extends rpc{

  final function GET():string{
    return 'IDL.html';
  }

  final function POST():void{
    (new \Yar_Server($this))->handle();
    //die;//FIXME 后续的请求头控制一律忽略掉了
  }

}
