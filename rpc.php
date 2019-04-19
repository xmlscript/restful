<?php namespace srv; // vim: se fdm=marker:

abstract class rpc extends api{

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
