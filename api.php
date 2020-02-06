<?php namespace srv; // vim: se fdm=marker:

abstract class api{

  /**
   * @TODO 仅仅代理执行目标方法，其余交给各自子类的toString实现
   */
  final function __invoke(string $verb):string{
    try{
      ob_start();
      if(method_exists($this,$verb))
        switch($verb){

        case 'HEAD':
        case 'OPTIONS':
          static::$verb();
          return '';

        case 'PUT':
        case 'PATCH':
          $ret = static::$verb(...$this->query2parameters($verb, $_GET));
          if(ob_get_length() && !is_null($ret)) throw new \Error('Internal Server Error',500);
          http_response_code($ret?204:202);
          return '';

        case 'TRACE':
        case 'CONNECT'://有payload
          //FIXME 不要干扰web容器自身的实现
          break;

        case 'GET':
        case 'POST':
        case 'DELETE'://WebDAV
          $ret = $this->vary(static::$verb(...$this->query2parameters($verb, $_GET)));
          if(ob_get_length() && !is_null($ret)) throw new \Error('Internal Server Error',500);
          return $ret;
          header('Content-Length: '.strlen($ret));
          return $this->etag($ret);
        }
      throw new \BadMethodCallException('Not Implemented',501);
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

      $ret = $this->vary([
        'code'=>(int)$code==(float)$code?(int)$code:(float)$code,
        'reason'=>(string)$reason,
        'msg'=>$t->getMessage(),
      ]);

      header('Content-Length: '.strlen($ret));
      return $ret;
    }finally{
      ob_end_clean();
      $this->CORS();
    }
  }


  /**
   * 执行完目标方法之后立即收集headers，以免暴露太多内部使用的header
   *
   */
  final private function CORS():void{#{{{

    if(isset($_SERVER['HTTP_ORIGIN'])){

      //FIXME 有必要暴露这些吗？X-Powered-By, Vary, Content-Length, ETag, Origin
      header('Access-Control-Expose-Headers: '.implode(array_filter(array_map(function($v){
        $v = strstr($v,':',true);
        return preg_grep("/$v/i",['Cache-Control','Content-Language','Content-Type','Expires','Last-Modified','Pragma','X-Powered-By'])?null:$v;
      },headers_list())),', '),false);

      header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");

      if(count($_COOKIE) || static::header('Set-Cookie'))
        header("Access-Control-Allow-Credentials: true");

      header('Vary: Origin',false);

    }

  }#}}}


  //FIXME 不要考虑Swoole
  final protected static function header(string $str):?string{
    foreach(array_reverse(headers_list()) as $item){
      [$k,$v] = explode(':',$item,2);
      if(strcasecmp($str, $k)===0)
        return trim($v);
    }
    return null;
  }


  /**
   * @todo 一组数据，如何设置Last-Modified，以及如何同时处理对应的If-Modified-Since
   * @fixme 内容协商自带Vary: negotiate和Content-Location，如何覆盖？
   */
  final private function etag(string &$payload):string{#{{{
    if(
      isset($payload) &&
      !headers_sent() &&
      !in_array(http_response_code(),[304,412,204,206,416])
    ){

      $etag = '"'.crc32(join(headers_list()).$payload).'"';

      $comp = function(string $etag, ?string $IF, bool $W=true):bool{
        return $IF && in_array($etag, array_map(fn($v)=> ltrim($v," $W"?'W/':''),explode(',',$IF)));
      };

      if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $comp($etag,$_SERVER['HTTP_IF_NONE_MATCH'])){
        http_response_code(304);
        return '';
      }else
        header("ETag: $etag");


    }

    return $payload;

  }#}}}


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
    //if($method && method_exists($this,$method))
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

}
