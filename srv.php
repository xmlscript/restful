<?php namespace http; // vim: se fdm=marker:

/**
 * @todo 兼容cli模式，至少不能报错
 */
abstract class srv{

  final function __invoke(string $verb){
    if(!in_array(strtoupper($verb),self::method())) throw new \BadMethodCallException('Not Implemented',501);
    ob_start();
    $ret = static::$verb(...self::query2parameters($verb, $_GET));
    if(ob_get_length()){
      ob_end_clean();
      throw new \Error('-1 Internal Server Error',500);
    }
    ob_end_clean();
    return $ret;
  }

  final function __toString():string{//{{{
    try{
      $ret = $this($_SERVER['REQUEST_METHOD'])??'';

      if(!is_string($ret)){
        header('Content-Type: application/json;charset=UTF-8');
        $ret = json_encode($ret, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
      }
    }catch(\Throwable $t){
      header_remove();
      http_response_code(max(-1,$t->getCode())?:500);

      if($t->getCode() === 0){
        $code = 0;
        $reason = 'Internal Server Error';
      }elseif(!sscanf($t->getMessage(),'%e%[^$]',$code,$reason)){
        $code = 0;
        $reason = trim($t->getMessage());
      }

      header('Content-Type: application/json;charset=UTF-8');
      $ret = json_encode([
        'code'=>(int)$code==(float)$code?(int)$code:(float)$code,
        'reason'=>(string)$reason,
      ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);

    }finally{

      //FIXME 有可能被httpd的输出过滤器更改内容
      header('Content-Length: '.strlen($ret));

      if(isset($_SERVER['HTTP_ORIGIN'])){//CORS
        //FIXME 有必要暴露这些吗？X-Powered-By, Vary, Content-Length, ETag, Origin
        header('Access-Control-Expose-Headers: '.implode(array_filter(array_map(function($v){
          $v = strstr($v,':',true);
          return preg_grep("/$v/i",['Cache-Control','Content-Language','Content-Type','Expires','Last-Modified','Pragma','X-Powered-By'])?null:$v;
        },headers_list())),', '),false);

        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");

        if(count($_COOKIE) || self::header('Set-Cookie'))
          header("Access-Control-Allow-Credentials: true");

        header('Vary: Origin',false);
      }


      if(
        !headers_sent() &&
        !in_array(http_response_code(),[304,412,204,206,416])
      ){

        $etag = '"'.crc32($ret).'"';

        $comp = function(string $etag, ?string $IF, bool $W=true):bool{
          return $IF && in_array($etag, array_map(fn($v)=> ltrim($v," $W"?'W/':''),explode(',',$IF)));
        };

        if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $comp($etag,$_SERVER['HTTP_IF_NONE_MATCH']))
          http_response_code(304);
        else
          header("ETag: W/$etag");
      }

      //TODO POST时将携带If-Match，如果无法匹配对应的etag，则表示内容已被篡改，返回412
      //TODO ETag不该包括header，而且应该独立函数供存取

      if(self::header('Content-Type')) header('X-Content-Type-Options: nosniff');

      if(isset($_SERVER['SSL_SERVER_V_END']))//FIXME nginx是否正确实现
        header('Strict-Transport-Security: max-age='.(strtotime($_SERVER['SSL_SERVER_V_END'])-time()).'; includeSubDomains; preload');
      //FIXME 后缀的preload，能否这样用，有什么作用？

      //SEE https://developer.mozilla.org/zh-CN/docs/Web/HTTP/Caching_FAQ
      //SEE https://developer.mozilla.org/zh-CN/docs/Web/HTTP/Headers/Cache-Control
      //header('Cache-Control: no-cache, no-store, must-revalidate');
      //header('Cache-Control: public, s-maxage=600, max-age=60');
      //header('Cache-Control: public, max-age=600');//FIXME 与php原生session的冲突？

      if(isset($_SERVER['HTTP_LAST_EVENT_ID']));

      return $ret;
    }
  }//}}}

  final private static function method():array{
    return array_map('strtoupper',array_filter(array_column((new \ReflectionClass(static::class))->getMethods(\ReflectionMethod::IS_PUBLIC),'name'),'ctype_alpha'));
  }


  final function OPTIONS($age=600):void{#{{{
    if(isset($_SERVER['HTTP_ORIGIN'],$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])){

      header('Access-Control-Max-Age: '.is_numeric($age)&&settype($age,'int')?$age:600);
      header('Access-Control-Allow-Methods: '.implode(', ',self::method()));

      if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']) &&
        method_exists(static::class,$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
      header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

      header('Access-Control-Allow-Credentials: true');//FIXME 尚未执行目标方法之前，无法获知

    }
  }#}}}


  final function HEAD(){
    return static::GET(...self::query2parameters('GET', $_GET));
  }

  function GET(){}


  final private static function header(string $str):?string{
    foreach(array_reverse(headers_list()) as $item){
      [$k,$v] = explode(':',$item,2);
      if(strcasecmp($str, $k)===0)
        return trim($v);
    }
    return null;
  }


  final private static function check(?string $type, &$value, string $name, bool $allowsNull):\Generator{#{{{
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

  final private static function query2parameters(string $method, array $args):\Generator{#{{{
    //if($method && method_exists($this,$method))
    foreach((new \ReflectionMethod(static::class,$method))->getParameters() as $param){
      $name = strtolower($param->name);
      if(isset($args[$name])){
        [$type,$allowsNull] = [(string)$param->getType(), $param->allowsNull()];
        if($param->isVariadic()){
          if(is_array($args[$name]))
            foreach($args[$name] as $v)
              yield from self::check($type, $v, $name, $allowsNull);
          else//可变长参数，变通一下，允许不使用数组形式，而直接临时使用标量
            yield from self::check($type, $args[$name], $name, $allowsNull);
        }else{//必然string
          yield from self::check($type, $args[$name], $name, $allowsNull);
        }
      }elseif($param->isOptional() && $param->isDefaultValueAvailable())
        yield $param->getDefaultValue();
      else
        throw new \InvalidArgumentException("缺少必要的查询参数{$name}",400);
    }
  }#}}}

}
