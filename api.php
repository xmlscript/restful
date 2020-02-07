<?php namespace http; // vim: se fdm=marker:

abstract class srv{

  function __toString(){
    return $this($_SERVER['REQUEST_METHOD']);
  }


  /**
   * 子类自行实现的所有public方法都是verb
   */
  final private function method():array{
    //FIXME 私有方法？
    return array_filter(['GET','OPTIONS','POST','PUT','PATCH','DELETE'],fn($m) => method_exists(static::class,$m));
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
      header('Access-Control-Allow-Methods: '.implode(', ',$this->method()));

      if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']) &&
        method_exists(static::class,$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
      header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

      header('Access-Control-Allow-Credentials: true');//FIXME 尚未执行目标方法之前，无法获知

    }
    http_response_code(204);
  }#}}}

  final function HEAD():void{
    static::GET(...$this->query2parameters('GET', $_GET));
  }

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
        case 'DELETE':
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


  final static function vary($data):string{

    header('Vary: Accept',false);

    switch(gettype($data)){
    case 'NULL':
    case 'string':
    case 'integer':
    case 'double':
      return (string)$data;
    case 'boolean':
    case 'object':
    case 'resource':
    case 'array':
    default:
    }

    if(is_null($data) || is_string($data)) return (string)$data;

    $content_type = self::header('Content-Type');
    $ACCEPT = explode(';',$content_type,2)[0]?:$_SERVER['HTTP_ACCEPT']??ini_get('default_mimetype').',*/*;q=0.1';
    $charset = substr(stristr($content_type,'charset='),8)?:ini_get('default_charset')?:'UTF-8';

    if(is_resource($data))
      switch(get_resource_type($data)){
        case 'gd':
          $ACCEPT .= ',,,image/*;q=.3';
          break;

        case 'curl'://FIXME 如果是ftp等其他非http协议？
          header('Cache-Control: no-cache');
          //$ACCEPT .= ',text/plain;q=.3';
          self::header('Content-Type') || header('Content-Type: text/plain');
          curl_setopt($data, CURLOPT_RETURNTRANSFER, true);
          return curl_exec($data);

        case 'stream': //FIXME 无法区分fopen与opendir，幸好opendir没有副作用
          self::header('Content-Type') || header('Content-Type: text/plain');
          return stream_get_contents($data);

        default:
          throw new \UnexpectedValueException('Unexpected Value',500);
      }
    elseif($data instanceof \SimpleXMLElement || $data instanceof \DOMDocument){
      //FIXME XML格式太丰富了，既然开发者耗费精力准备好了XML对象，不如就直接输入
      self::header('Content-Type') || header("Content-Type: application/xml;charset=$charset");
      return $data->saveXML();
    }elseif($data instanceof \Iterator){
      //FIXME yeid可以多次return不同类型，不能贸然转换，因为不知道开发者实现目的
      //FIXME 白白浪费了yield性能
      //TODO 不如让各自MIME自行判断，仍然yield
      $data = iterator_to_array($data);
    }

    foreach(array_keys(self::q($ACCEPT)) as $item)
      switch(strtolower($item)){#{{{

        case 'image/*':
        case 'image/png':
          if(imagetypes() & IMG_PNG){
            //TODO
          }
        case 'image/bmp':
          if(imagetypes() & IMG_BMP){
            //TODO
          }
        case 'image/gif':
          if(imagetypes() & IMG_GIF){
            //TODO
          }
        case 'image/webp':
          /**/
          if(is_resource($data) && get_resource_type($data)==='gd' && imagetypes() & IMG_WEBP){
            if(!imageistruecolor($data)){//因为webp必须由truecolor创建
              $tmp = imagecreatetruecolor(imagesx($data),imagesy($data));
              imagecopy($tmp,$data,0,0,0,0,imagesx($data),imagesy($data));
              imagedestroy($data);
              $data = $tmp;
              $tmp = null;
            }
          }
          /**/
        case 'image/jpeg':
          if(imagetypes() & IMG_JPEG){
            //TODO
          }

          if(is_resource($data) && get_resource_type($data)==='gd'){

            $fmt = str_replace('*','png',substr($item,6));
            self::header('Content-Type') || header("Content-Type: image/$fmt");
            //ob_start();
            imagecolorstotal($data) || imagecolorallocate($data,222,222,222);
            ('image'.$fmt)($data); //能否预输出到一个stream
            //$buf = ob_get_contents();
            //ob_end_clean();
            //return $buf;
          }else break;


        case 'text/event-stream'://仅限于GET方法
          header("Content-Type: text/event-stream;charset=$charset");
          header('Cache-Control: no-cache');

          if($data instanceof \Google\Protobuf\Message)
            $content = $data->toJsonString(); //FIXME 序列化字符串
          else
            $content=json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);

          $id = crc32($content);
          $retry = $_GET['retry']&&is_numeric($_GET['retry'])&&settype($_GET['retry'])?(int)$_GET['retry']:3000;

          if(isset($_SERVER['HTTP_LAST_EVENT_ID']) && $_SERVER['HTTP_LAST_EVENT_ID']==$id)//把ID当作ETag来使用
            return 'retry: '.++$_SERVER['HTTP_LAST_EVENT_ID']."\n\n";//TODO 按需要自动延长retry时间
          else
            return "id: $id\ndata: $content\nretry: $retry\n\n";


        case 'application/xml':
        case 'text/xml':
          if($data instanceof \PDOStatement){
            //break;
            //FIXME 如果开发者查询count，或者
            header("Content-Type: $item;charset=$charset");
            header('Content-Type: text/plain');
            $data->execute();
            $arr = $data->fetchAll(\PDO::FETCH_ASSOC);
            var_dump($arr);
            die('//TODO array to xml');
          }else break;


          //TODO 如果office打开无需验证的接口，则保留，否则失去存在的意义
        case 'text/csv':
          if($data instanceof \PDOStatement){
            header("Content-Type: text/csv;charset=$charset");
            return '$data->fetchAll()';
          }//故意没有break

        case 'text/*':
        case 'text/plain':
          header("Content-Type: text/plain;charset=$charset");
          //TODO 生成填充数据的sql
          break;

        case '*/*':
        case 'application/json':
          if($data instanceof \Google\Protobuf\Message){
            //FIXME 暴殄天物，好端端的二进制，硬生生拆散成string
            header("Content-Type: application/json;charset=$charset");
            return $data->toJsonString();
          }elseif($data instanceof \PDOStatement){
            header("Content-Type: application/json;charset=$charset");
            return json_encode($data->fetchAll(\PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
          }elseif($str=json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION)){
            header("Content-Type: application/json;charset=$charset");
            return $str;
          }else break;

      }#}}}

    throw new \Error('Internal Server Error',500);
  }


  //FIXME q乱序识别错误
  final private static function q(string $str=''):array{#{{{
    $result = $tmp = [];
    foreach(explode(',',$str) as $item){
      if(strpos($item,';')===false){
        $tmp[] = $item;//暂存
      }else{
        $tmp[] = strstr($item,';',true);
        $q = filter_var(explode('q=',$item)[1], FILTER_VALIDATE_FLOAT);
        if($q!==false&&$q>0&&$q<=1)//合法float就存入最终结果，否则不存，反正最后要清空这一轮的暂存期
          foreach($tmp as $v)
            $result[$v] = $q;
        $tmp = [];//无论如何，本轮结束清空暂存区
      }
    }
    $result += array_fill_keys(array_filter(array_map('trim',$tmp)),0.5);
    arsort($result);
    return $result?:['*/*'=>0.5];
  }#}}}


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
