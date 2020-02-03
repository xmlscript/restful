<?php namespace srv; // vim: se fdm=marker:

abstract class api{

  abstract protected function method():array;

  final function __call($verb,$arg):void{
    if(strcasecmp($verb,'GET') && strcasecmp($verb,'HEAD'))
    throw new \BadMethodCallException('Not Implemented',501);
  }

  final function __invoke(string $verb):string{
    try{
      ob_start();
      switch($verb){
      case 'HEAD':
      case 'GET':
        $ret = $this->vary(static::GET(...$this->query2parameters('GET', $_GET)));
        if(ob_get_length()) throw new \Error('Internal Server Error',500);
        header('Content-Length: '.strlen($ret));
        if(empty($ret) && http_response_code()===200) http_response_code(204);
        $this->etag($ret);
        return $ret;

      case 'OPTIONS'://WebDAV CalDAV
        header('DASL: <DAV:sql>');
        header('DAV: 1 ,2');
        //列出服务器支持的所有verb
        header('Public: OPTIONS, TRACE, GET, HEAD, DELETE, PUT, POST, COPY, MOVE, MKCOL, PROPFIND, PROPPATCH, LOCK, UNLOCK， SEARCH');
        //仅列出对当前资源适用的verb
        header('Allow: OPTIONS, TRACE, GET, HEAD, DELETE, PUT, COPY, MOVE, MKCOL, PROPFIND, PROPPATCH, LOCK, UNLOCK， SEARCH');
        return $this->OPTIONS();

      case 'DELETE'://WebDAV
        http_response_code(207);//204, 207, 423
        $_SERVER['HTTP_IF'];
        break;

      case 'PUT'://WebDAV CalDAV

      case 'PATCH':
      case 'POST':
        $ret = static::{$verb}(...$this->query2parameters($verb, $_GET));
        if(ob_get_length()) throw new \Error('Internal Server Error',500);
        http_response_code($ret?204:202);
        return '';

      case 'TRACE':
      case 'CONNECT':
        //FIXME 不要干扰web容器自身的实现

      case 'PROPFIND'://获取一个或多个文件的属性，可以请求所有属性kv，一组属性kv，或所有属性k
        $_SERVER['HTTP_DEPTH'];
        http_response_code(207);//200, 207, 403, 404
        return '<xml>';
        break;
      case 'PROPPATCH'://对指定的资源原子化设置或删除多个属性
        http_response_code(207);//200, 207, 401, 403, 409, 423, 507
        $_SERVER['HTTP_IF'];
        break;
      case 'MKCOL':
        http_response_code(201);//201, 207, 403, 405, 409, 415, 422, 507
        break;

      case 'COPY':
        http_response_code(207);//102, 201, 204, 207, 403, 409, 412, 423, 502, 507

      case 'MOVE'://比COPY多一步骤，复制后检查完整性，然后删除原始资源
        http_response_code(207);//102, 201, 204, 207, 403, 409, 412, 423, 502
        $_SERVER['HTTP_DESTINATION'];
        $_SERVER['HTTP_OVERWRITE'];
        $_SERVER['HTTP_DEPTH'];
        $_SERVER['HTTP_IF'];
        break;

      case 'LOCK':
        $_SERVER['HTTP_IF'];

      case 'UNLOCK':
        $_SERVER['HTTP_IF'];

      //https://tools.ietf.org/html/rfc4791
      case 'REPORT'://CalDAV
      case 'MKCALENDAR'://CalDAV

      default:
        //FIXME 要允许public自定义方法，但防止xss
        //FIXME 自定义verb的返回值和异常，不适用于vary，怎么解决？
        throw new \BadMethodCallException('Not Implemented',501);
      }
    }catch(\Throwable $t){

      http_response_code(max(-1,$t->getCode())?:500);

      if($t->getCode() === 0){
        $code = 0;
        $reason = 'Internal Server Error';
      }elseif(!sscanf($t->getMessage(),'%e%[^$]',$code,$reason)){
        $code = 0;
        $reason = trim($t->getMessage());
      }

      $ret = $this->vary([
        'code'=>(int)$code===(float)$code?(int)$code:(float)$code,
        'reason'=>(string)$reason,
      ]);

      header('Content-Length: '.strlen($ret));
      return $ret;
    }finally{
      ob_end_clean();
    }
  }


  private function check(?string $type, &$value, string $name, bool $allowsNull):\Generator{#{{{
    switch($type){
      case null:
      case 'string':
        if(empty($value) && $allowsNull)
          yield;
        else
          yield $value;
        break;
      case 'bool':
          yield filter_var($value===''?:$value, FILTER_VALIDATE_BOOLEAN);
        break;
      case 'int':
        if(is_numeric($value)){
          if($value<PHP_INT_MIN || $value>PHP_INT_MAX)
            throw new \RangeException($name.' Allow range from '.PHP_INT_MIN.' to '.PHP_INT_MAX,400);
          else
            yield $value;
        }elseif(empty($value) && $allowsNull)
          yield;
        else
          throw new \InvalidArgumentException("无法将{$name}='{$value}'转换成{$type}",400);
        break;
      case 'float':
        if(empty($value) && $allowsNull)
          yield;
        elseif(!is_numeric($value))
          throw new \InvalidArgumentException("无法将{$name}='{$value}'转换成{$type}",400);
        else
          yield $value;
        break;
      case 'array':
        yield (array)$value;
        break;
      case \DateTime::class:
        try{
          yield new \DateTime($value);
        }catch(\Exception $e){
          throw new \InvalidArgumentException("无法将{$name}='{$value}'转换成{$type}",400,$e);
        }
        break;
      case \DateTimeImmutable::class:
      case \DateTimeInterface::class:
        try{
          yield new \DateTimeImmutable($value);
        }catch(\Exception $e){
          throw new \InvalidArgumentException("无法将{$name}='{$value}'转换成{$type}",400,$e);
        }
        break;
      default:
        throw new \InvalidArgumentException('基类死规定，就一个str能怎么转换那么多种type',500);
    }
  }#}}}

  final private function query2parameters(string $method, array $args):\Generator{#{{{
    if($method && method_exists($this,$method))
    foreach((new \ReflectionMethod($this,$method))->getParameters() as $param){
      $name = strtolower($param->name);
      if(isset($args[$name])){
        [$type,$allowsNull] = [(string)$param->getType(), $param->allowsNull()];
        if($param->isVariadic()){
          if(is_array($args[$name]))
            foreach($args[$name] as $v)
              yield from $this->check($type, $v, $name, $allowsNull);
          else//可变长参数，变通一下，允许不使用数组形式，而直接临时使用标量
            yield from $this->check($type, $args[$name], $name, $allowsNull);
        }else{//必然string
          yield from $this->check($type, $args[$name], $name, $allowsNull);
        }
      }elseif($param->isOptional() && $param->isDefaultValueAvailable())
        yield $param->getDefaultValue();
      else
        throw new \InvalidArgumentException("缺少必要的查询参数{$name}",400);
    }
  }#}}}


  final private function etag(string $payload):void{#{{{
    if(
      isset($payload) &&
      !headers_sent() &&
      !in_array(http_response_code(),[304,412,204,206,416])
    ){

      $etag = '"'.crc32(ob_get_contents().join(headers_list()).$payload).'"';//算法仅此一处

      $comp = function(string $etag, ?string $IF, bool $W=true):bool{
        return $IF && in_array($etag, array_map(function($v) use ($W){
          return ltrim($v,' '.$W?'W/':'');
        },explode(',',$IF)));
      };

      if(
        isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
        $comp($etag,$_SERVER['HTTP_IF_NONE_MATCH'])
      ){
        http_response_code(304);
      }else
        header("ETag: $etag");

    }

  }#}}}


  /**
   * @todo 反射并生成一个标准文档，然后用模板转换成html/cli打印格式
   * @fixme restful不能独占GET
   */
  final function __debugInfo():array{#{{{
    $data = [];
    $data['doc'] = $this->parse((new \ReflectionClass(static::class))->getDocComment());
    foreach($this->method() as $method=>$ref){
      $arr = [
        'method' => $method,
      ];

      //TODO 抓注释
      $arr['doc'] = $this->parse($ref->getDocComment());

      //TODO 抓参数
      foreach($ref->getParameters() as $param){
        $a['name'] = $param->name;
        $a['optional'] = $param->isOptional();
        //$a['default'] = $param->getDefaultValue();
        $a['hasType'] = $param->hasType();
        $a['type'] = $param->hasType().'';
        $a['isVariadic'] = $param->isVariadic();
        $a['isArray'] = $param->isArray();
        $arr['params'][] = $a;
      }
      $data['api'][] = $arr;
    }
    return $data;
  }#}}}

  final private function &parse(string $doc):array{#{{{
    //header('Content-Type: text/html;charset=utf-8');
    preg_match_all('#@([a-zA-Z]+)\s*([a-zA-Z0-9, ()_].*)#', $doc, $matches, PREG_SET_ORDER);
    $arr = [];
    foreach($matches as $item){

    }
    array_walk($matches, function(&$v){
      $v = ['tag'=>$v[1],'value'=>$v[2]];
    });
    return $matches;
  }#}}}

}
