<?php namespace srv; // vim: se fdm=marker:

class yar extends rpc{

  final function GET():string{
    header('Content-Type: text/plain;charset=utf-8');
    return preg_replace(['/\n\s+@@ .*\n/','/\n\s{3,}\*/'],['x',"\n     *"],new \ReflectionClass(static::class));
  }

  final function POST():void{
    (new \Yar_Server($this))->handle();
  }

}
