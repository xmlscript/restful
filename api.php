<?php namespace srv; // vim: se fdm=marker:

/**
 * @todo 集成swoole
 * @todo swoole路由require之后，仅载入class，屏蔽后续操作！！！
 */
abstract class api{

  /**
   * @todo cli模式，一套原生代码兼容swoole
   * @todo 如果带参数，有可能是为了web-hook
   * @todo 高亮console
   */
  function __construct(){

  }

  abstract function __toString():string;

  abstract function __invoke();

  abstract protected function method():array;


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
