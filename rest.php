<?php namespace srv; // vim: se fdm=marker:

class rest extends api{

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


  //FIXME
  final function HEAD(){
    $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'GET';
    return $this();
  }



  final private function CORS():void{#{{{
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


    if(isset($_SERVER['HTTP_ORIGIN']) && is_scalar($_SERVER['HTTP_ORIGIN'])){
      header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
      header("Access-Control-Allow-Credentials: true");
      header('Vary: Origin');
    }

  }#}}}


  final function __toString():string{
    try{
      return $payload = $this->vary($this());
    }catch(\Throwable $t){
      ob_get_length() and ob_clean(); //FIXME 测试OB未启用时的情况

      //TODO 建议开发者抛出统一编写的业务异常，内置硬编码message和code

      if(http_response_code()===200){
        $code = $e->getCode();
        http_response_code($code>=400&&$code<600?$code:500);
      }

      header_remove('Content-Type');
      return $payload = $this->vary([
        'code' => $e->getCode(),
        'reason' => $e->getMessage(),
      ]);
    }

    finally{
      isset($payload) || http_response_code()===200 && http_response_code(204);
    }

  }


  final private function vary($data):string{

    if(is_null($data)) return '';

    $content_type = self::header('Content-Type');
    $ACCEPT = explode(';',$content_type,2)[0]?:$_SERVER['HTTP_ACCEPT']??ini_get('default_mimetype').',*/*;q=0.1';
    $charset = substr(stristr($content_type,'charset='),8)?:ini_get('default_charset')?:'UTF-8';

    if(is_resource($data))
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
    elseif($data instanceof \DateTime)
      $data = $data->format(DATE_ISO8601); //js的date方法只认ISO8601或RFC2822
    elseif($data instanceof \SimpleXMLElement || $data instanceof \DOMDocument){
      //FIXME XML格式太丰富了，既然开发者耗费精力准备好了XML对象，不如就直接输入
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
          }elseif(is_string($data)){//TODO 相信开发者，不要浪费资源判断是否合法xml了
            header("Content-Type: $item;charset=$charset");
            return (string)$data;
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
          //TODO 生成填充数据的sql
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

}
