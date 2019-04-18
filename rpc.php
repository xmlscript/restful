<?php namespace srv; // vim: se fdm=marker:

abstract class rpc extends api{

  /**
   * @description soap需要class.wsdl，grpc需要class.proto，yar需要IDL.html
   */
  abstract function GET():string;

  abstract function POST():void;


  /**
   * @fixme 基类catch仍使用vary来转换reason，但此时客户端不需要reason
   * GET方法使用了vary(string)
   * POST方法在catch时，仍使用了vary(['code','reason'])
   * soap会自动转换SoapFault，而yar无需响应体
   */
  final protected function vary($data):string{
    if(is_string($data)){
      return $data;
    }else
      http_response_code(500);
      return '';
  }

}
