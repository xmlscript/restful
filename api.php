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

      if(isset($_SERVER['REQUEST_METHOD'])&&$_SERVER['REQUEST_METHOD']==='GET'&&$_SERVER['QUERY_STRING']==='?')
        return $payload = '<h1>Docs</h1><p>'.$_SERVER['REQUEST_URI'];
      elseif(isset($_SERVER['REQUEST_METHOD'])&&$_SERVER['REQUEST_METHOD']==='GET'&&$_SERVER['QUERY_STRING']==='??')
        return $payload = '<h1>Docs</h1><p>'.$_SERVER['REQUEST_URI'].'<style>body{background:#eee}</style>';
      elseif(isset($_SERVER['REQUEST_METHOD'])&&$_SERVER['REQUEST_METHOD']==='GET'&&$_SERVER['QUERY_STRING']==='!')
        $proxy = $this->__debugInfo();

      elseif(in_array($method=$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']??$_SERVER['REQUEST_METHOD']??null,array_keys($this->method()),true))
        $proxy = static::$method(...$this->query2parameters($method, $_GET));
      else
        throw new \BadMethodCallException('Not Implemented',501);


      if(isset($_SERVER['HTTP_ORIGIN']) && is_scalar($_SERVER['HTTP_ORIGIN'])){
        header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
        header("Access-Control-Allow-Credentials: true");
        header('Vary: Origin');
      }

      return $payload = $this->vary($proxy);
    }#}}}

    catch(\Throwable $e){
      ob_get_length() and ob_clean();
      http_response_code($e->getCode()?:500) and header_remove('Content-Type');
      return $payload = $this->vary(['code'=>$e->getCode()?:500,'reason'=>$e->getCode()?$e->getMessage():'']);
    }

    finally{#{{{

      if(empty($payload) && http_response_code()===200){
        http_response_code(204);
        return null;
      }

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
      case 'DateTime':
        try{
          yield new \DateTime($value); //FIXME 语言特定的内置对象，不通用！
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


  final private function vary($data):?string{


    $content_type = self::header('Content-Type');
    $ACCEPT = explode(';',$content_type,2)[0]?:$_SERVER['HTTP_ACCEPT']??ini_get('default_mimetype').',*/*;q=0.1';
    $charset = substr(stristr($content_type,'charset='),8)?:ini_get('default_charset')?:'UTF-8';

    if(is_resource($data)){
      switch(get_resource_type($data)){
        case 'gd':
          $ACCEPT .= ',,,image/*;q=.3';
          break;

        case 'curl':
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
    }elseif($data instanceof \SimpleXMLElement || $data instanceof \DOMDocument){
      header("Content-Type: application/xml;charset=$charset");
      return $data->saveXML();
    }elseif($data instanceof \Iterator){
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
          if(imagetypes() & IMG_WEBP){
            //TODO
          }
        case 'image/jpeg':
          if(imagetypes() & IMG_JPEG){
            //TODO
          }

          if(is_resource($data) && get_resource_type($data)==='gd'){
            $fmt = str_replace('*','png',substr($item,6));
            self::header('Content-Type') || header("Content-Type: image/$fmt");
            ob_start();
            imagecolorstotal($data) || imagecolorallocate($data,222,222,222);
            ('image'.$fmt)($data); //能否预输出到一个stream
            $buf = ob_get_contents();
            ob_end_clean();
            return $buf;
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
            header("Content-Type: $item;charset=$charset");
            header('Content-Type: text/plain');
            $data->execute();
            $arr = $data->fetchAll(\PDO::FETCH_ASSOC);
            var_dump($arr);
            die('//TODO array to xml');
          }else break;


        case 'text/*':
        case 'text/csv':
          if(is_array($data)){
            header("Content-Type: text/csv;charset=$charset");
            //TODO 必须是整齐的二维数组，用tab分割
            return 'TODO csv';
          }elseif($data instanceof \PDOStatement){
            header("Content-Type: text/csv;charset=$charset");
            return '$data->fetchAll()';
          }//故意没有break

        case 'text/plain':
          header("Content-Type: text/plain;charset=$charset");
          break;


        case '*/*':
        case 'application/json':
          if($data instanceof \Google\Protobuf\Message){
            header("Content-Type: application/json;charset=$charset");
            return $data->toJsonString();
          }elseif(is_string($data)&&strlen($data)>1&&$data[0]==='"'&&$data[-1]==='"'&&is_string(json_decode($data,false,1))){
            header("Content-Type: application/json;charset=$charset");
            return $data;
          }elseif($data instanceof \PDOStatement){
            header("Content-Type: application/json;charset=$charset");
            return json_encode($data->fetchAll(\PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
          }elseif($str=json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION)){
            header("Content-Type: application/json;charset=$charset");
            return $str;
          }else break;

      }#}}}

    if(self::header('Content-Type')&&(is_string($data)||is_numeric($data)||is_null($data))){
      return $data;
    }elseif($data instanceof \Google\Protobuf\Message){
      header("Content-Type: application/octet-stream");
      return $data;//TODO 序列化
    }else throw new \Error('Not Accepted',406);
  }


  final private static function header(string $str):?string{
    foreach(array_reverse(headers_list()) as $item){
      [$k,$v] = explode(':',$item,2);
      if(strcasecmp($str, $k)===0)
        return trim($v);
    }
    return null;
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
