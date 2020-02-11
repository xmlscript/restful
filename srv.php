<?php namespace http; // vim: se fdm=marker:

abstract class srv{

  final function __invoke(string $verb){
    if(!in_array(strtoupper($verb),self::method())) throw new \BadMethodCallException('Not Implemented',501);
    return static::$verb(...self::query2parameters($verb, $_GET));
  }

  final function __toString():string{//{{{
    try{
      ob_start();
      $ret = $this($_SERVER['REQUEST_METHOD']);
      if(ob_get_length()) throw new \Error('-1 Internal Server Error',500);
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
      ob_end_clean();

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
      ){//ETag

        $etag = '"'.crc32(join(headers_list()).$ret).'"';

        $comp = function(string $etag, ?string $IF, bool $W=true):bool{
          return $IF && in_array($etag, array_map(fn($v)=> ltrim($v," $W"?'W/':''),explode(',',$IF)));
        };

        if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $comp($etag,$_SERVER['HTTP_IF_NONE_MATCH']))
          http_response_code(304);
        else
          header("ETag: $etag");
      }


      return $ret;
    }
  }//}}}

  final private static function method():array{
    return array_map('strtoupper',array_filter(array_column((new \ReflectionClass(static::class))->getMethods(\ReflectionMethod::IS_PUBLIC),'name'),'ctype_alpha'));
  }

  /**
   * CORS适用于
   * xhr,fetch调用
   * @font-face加载
   * WebGL贴图
   * <canvas>drawImage加载
   *
   * 以下安全范围之内，不会触发OPTIONS
   * HEAD,GET,POST
   * Accept,Accept-Language, Content-Language,
   * DPR, Downlink, Save-Data, Viewport-Width, Width
   * Last-Event-ID, 
   * Content-Type: application/x-www-form-urlencoded, multipart/form-data, text/plain
   * xhrUpload对象不能注册listener
   * 不能使用ReadableStream对象
   */
  final function OPTIONS($age=600):void{#{{{
    if(isset($_SERVER['HTTP_ORIGIN'],$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])){

      header('Access-Control-Max-Age: '.is_numeric($age)&&settype($age,'int')?$age:600);
      header('Access-Control-Allow-Methods: '.implode(', ',self::method()));

      if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']) &&
        method_exists(static::class,$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
      header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

      header('Access-Control-Allow-Credentials: true');//FIXME 尚未执行目标方法之前，无法获知

    }
    http_response_code(204);
  }#}}}


  final function HEAD():void{
    $this->GET(...self::query2parameters('GET', $_GET));
  }

  function GET(){}



  //FIXME 不要考虑Swoole
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
