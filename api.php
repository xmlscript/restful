<?php namespace srv; // vim: se fdm=marker:

abstract class api{

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


  abstract function __toString():string;


  /**
   * 允许抛出异常，以便内部二次处理，但__toString()代表正式对外生产，就不能再异常了
   */
  final function __invoke():string{

    //FIXME 大小写？
    if(in_array($method=$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']??$_SERVER['REQUEST_METHOD']??null,array_keys($this->method()),true))
      return static::$method(...$this->query2parameters($method, $_GET));
    else
      throw new \BadMethodCallException('Not Implemented',501);

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

  final protected static function header(string $str):?string{
    foreach(array_reverse(headers_list()) as $item){
      [$k,$v] = explode(':',$item,2);
      if(strcasecmp($str, $k)===0)
        return trim($v);
    }
    return null;
  }


  //FIXME q乱序识别错误
  final protected static function q(string $str=''):array{#{{{
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


/**
 * 兼容cli模式的swoole
 */
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
