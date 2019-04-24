<?php namespace srv; // vim: se fdm=marker:

/**
 * @fixme 必须运行于http2协议之上，才能被其他client识别
 * @todo 未来可能支持web，需要从api直接继承
 */
class grpc extends rpc{

  final function GET():string{
    return 'class.proto';
  }

  final function POST():void{
    $_SERVER['HTTP_PATH'];// /package.serverName/methodName
    $_SERVER['HTTP_AUTHORITY'];//uri
    $_SERVER['HTTP_GRPC_TIMEOUT'];//15
    $_SERVER['HTTP_CONTENT_TYPE'];//application/grpc+proto
    $_SERVER['HTTP_GRPC_ENCODING'];//gzip | deflate | snappy
    $_SERVER['HTTP_AUTHORIZATION'];//Bearer y235.wef315yfh138vh31hv93hv8h3v

    header('grpc-encoding: gzip');
    header('Content-Type: application/grpc+proto');
    header('Message-Type: 同名proto');
    header('Service-Name: package.serverName');
    header('Status: 0');

    //FIXME 预计支持grpc+web协议（未来两年可能进入streams API）
    //https://github.com/grpc/grpc-web/blob/master/BROWSER-FEATURES.md
    //https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-WEB.md
    //header('Content-Type: application/grpc-web+[proto,json,thrift]');
    //header('Content-Type: application/grpc-web-text+[proto,thrift]');

  }

}
