<?php namespace srv; // vim: se fdm=marker:

class api{

  final private function method():array{
    $arr = [];
    foreach((new \ReflectionClass($this))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m){
      if(strpos($m->name,'__')!==0)
        if($m->class===self::class){
          if(ctype_upper($m->name))
            $arr[$m->name] = $m;
        }
        elseif(ctype_print($m->name))
          $arr[strtoupper($m->name)] = $m;
    }
    return $arr;
  }


  final function OPTIONS($age=600):void{#{{{

    $methods = implode(', ',array_keys($this->method()));

    if(
      isset($_SERVER['HTTP_ORIGIN'],$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) &&
      method_exists(static::class,$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])
    ){

      header('Access-Control-Max-Age: '.is_numeric($age)&&settype($age,'int')?$age:600);
      header("Access-Control-Allow-Methods: $methods");

      if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header('Access-Control-Allow-Headers: '.$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);

    }else
      header("Allow: $methods");

  }#}}}


  final function HEAD(){
    $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'GET';
    return $this();
  }


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


  final function __toString():string{
    return $this()??'';
  }


  final function __invoke():?string{

    try{#{{{

      if(in_array($method=$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']??$_SERVER['REQUEST_METHOD']??null,array_keys($this->method()),true))
        $proxy = static::$method(...$this->query2parameters($method, $_GET));
      else
        throw new \BadMethodCallException('Not Implemented',501);


      if(isset($_SERVER['HTTP_ORIGIN']) && is_scalar($_SERVER['HTTP_ORIGIN'])){
        header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
        header("Access-Control-Allow-Credentials: true");
        header('Vary: Origin');
      }


      $parse_content_type = function(?string $string):array{
        $charset=null;
        $mime = trim(explode(';',substr($string,13))[0]);
        if($pos=stripos($string, 'charset='))
          $charset = trim(explode(';',substr($string,$pos+8))[0]);
        return ['mime'=>$mime,'charset'=>$charset];
      };


      $header = function(string $str):?string{
        foreach(headers_list() as $item)
          if(strcasecmp(trim($str), trim(strstr($item,':',true)))===0)
            return ltrim(substr($item,strlen($str)));
        return null;
      };

      $content_type = $parse_content_type($header('Content-Type'));

      return $payload = is_null($proxy)||is_string($proxy)||is_numeric($proxy)?$proxy:$this->vary(
        $proxy,
        $content_type['mime']?:@$_SERVER['HTTP_ACCEPT'],
        $content_type['charset']
      );
    }#}}}

    catch(\Throwable $e){
      ob_get_length() and ob_clean();
      http_response_code($e->getCode()?:500) and header_remove('Content-Type');
      return $payload = $this->vary($e, @$_SERVER['HTTP_ACCEPT']);
      //return $payload = $this->vary(['code'=>$e->getCode()?:500,'reason'=>$e->getCode()?$e->getMessage():''],@$_SERVER['HTTP_ACCEPT']);
    }

    finally{#{{{

      if(
        !headers_sent() &&
        isset($_SERVER['HTTP_ORIGIN'],$_SERVER['HTTP_ACCEPT']) &&
        strcasecmp($_SERVER['HTTP_ACCEPT'],'text/event-stream') //不是text/event-stream
      ){
        header('Access-Control-Expose-Headers: '.implode(array_filter(array_map(function($v){
          $v = strstr($v,':',true);
          return preg_grep("/$v/i",['Cache-Control','Content-Language','Content-Type','Expires','Last-Modified','Pragma'])?null:$v;
        },headers_list())),','));
      }


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
          return '';
        }else
          header("ETag: $etag");

      }

    }#}}}

  }


  private function check(?string $type,&$value, string $name, bool $allowsNull):\Generator{#{{{
    switch($type){
      case null:
      case 'string':
        if(empty($value) && $allowsNull())
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
        }elseif(empty($value) && $allowsNull())
          yield;
        else
          throw new \InvalidArgumentException("无法将{$name}='{$value}'转换成{$type}",400);
        break;
      case 'float':
        if(empty($value) && $allowsNull())
          yield;
        elseif(!is_numeric($value))
          throw new \InvalidArgumentException("无法将{$name}='{$value}'转换成{$type}",400);
        else
          yield $value;
        break;
      case 'DateTime':
        try{
          yield new \DateTime($value);
        }catch(\Exception $e){
          throw new \InvalidArgumentException("无法将{$name}='{$value}'转换成{$type}",400,$e);
        }
        break;
      case 'array':
        yield (array)$value;
        break;
      default:
        throw new \InvalidArgumentException('基类死规定，就一个str能怎么转换那么多种type',500);
    }
  }#}}}


  final private function query2parameters(string $method, array $args):\Generator{#{{{
    //if(method_exists($this,$method))
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


  final private function q(string $str=null):array{#{{{
    if(empty($str)) return [];
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
    return $result;
  }#}}}


  final private function vary($data, string $ACCEPT=null, string $charset=null):?string{

    if(is_resource($data))
      throw new \UnexpectedValueException('Unexpected Value',500);
    elseif($data instanceof \Iterator)
      $data = iterator_to_array($data);

    $ACCEPT = $ACCEPT??ini_get('default_mimetype').',*/*';
    $charset = $charset??ini_get('default_charset');

    foreach(array_keys(self::q($ACCEPT)) as $item)
      switch(strtolower($item)){#{{{

        case 'text/html'://为了兼容传统action提交
          if($data instanceof \Throwable){
            return '<h1>Error '.http_response_code().'</h1>'.$data->getCode()?'<p>'.$data->getMessage():'';
          }elseif(is_scalar($data)||is_null($data))
            return $data;
          else
            break;

        case 'application/xml':
        case 'text/xml':
          if($data instanceof \SimpleXMLElement){
            header("content-type: application/xml;charset=$charset");
            return $data->saveXML();
          }else break;

        case '*/*':
        case 'application/json':
          header("content-type: application/json;charset=$charset");

          if($data instanceof \Throwable)
            return json_encode(['code'=>http_response_code(),'reason'=>$e->getCode()?$e->getMessage():''], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
          elseif($data instanceof \Google\Protobuf\Message)
            return $data->toJsonString();
          elseif($str=json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION)) return $str;
          else break;

        case 'application/x-yaml':
        case 'application/x.yaml':
        case 'application/vnd.yaml':
        case 'application/yaml':
        case 'text/x-yaml':
        case 'text/x.yaml':
        case 'text/vnd.yaml':
        case 'text/yaml':
          if(extension_loaded('yaml')){
            header("content-type: text/plain;charset=$charset");
            array_walk_recursive($data,function(&$v){$v=(is_object($v)&&!($v instanceof \DateTime))?(array)$v:$v;});
            return yaml_emit($data,YAML_UTF8_ENCODING);
          }else break;

        case 'text/event-stream'://仅限于GET方法
          header('Content-Type: text/event-stream;charset=UTF-8');
          header('Cache-Control: no-cache');

          $id = crc32($json=json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
          $retry = $_GET['retry']&&is_numeric($_GET['retry'])&&settype($_GET['retry'])?(int)$_GET['retry']:3000;

          if(isset($_SERVER['HTTP_LAST_EVENT_ID']) && $_SERVER['HTTP_LAST_EVENT_ID']==$id)//把ID当作ETag来使用
            return 'retry: '.++$_SERVER['HTTP_LAST_EVENT_ID']."\n\n";//TODO 按需要自动延长retry时间
          else
            return "id: $id\ndata: $json\nretry: $retry\n\n";

      }#}}}

    throw new \Error('Not Accepted',406);
  }

}

  function header(string $string, bool $replace=true, int $code=null):void{
    if(!headers_sent()){
      \header($string, $replace, $code);
      if(PHP_SAPI==='cli' && stripos($string,'HTTP/')!==0 && $objects=array_column(debug_backtrace(),'object'))
        (function(string $key, string $value, bool $replace){
          if($replace || empty($this->headers_list[$key]))
            $this->headers_list[$key] = $value;
          else
            $this->headers_list[$key] .= ", $value";
        })->call(
          end($objects),//FIXME reset第一个？next第二个？end最后一个？
          strtolower(strstr($string,':',true)),
          trim(substr($string, strpos($string, ':')+1)),
          $replace
        );
    }
  }


  function headers_list():array{
    if(PHP_SAPI==='cli'){
      return array_values(array_map(function($v,$k){
        return "$k: $v";
      },array_column(debug_backtrace(),'object')[-1]->headers_list??[]));
    }else return \headers_list();
  }


  function header_remove(string $name=null):void{
    headers_sent() or \header_remove($name);
    $objects=array_column(debug_backtrace(),'object');
    if(PHP_SAPI==='cli' && $obj=end($objects))
      if($name) unset($obj->headers_list[$name]);
      else unset($obj->headers_list);
  }
