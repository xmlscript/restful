<?php namespace srv; // vim: se fdm=marker:

abstract class api{

  abstract protected function method():array;

  final function __call($verb,$arg):void{
    if($verb!=='GET') throw new \BadMethodCallException('Not Implemented',501);
  }


  final function __invoke(string $verb):string{

    try{

      ob_start();
      switch($verb){

      case 'HEAD':
      case 'GET':
        $ret = $this->vary(static::GET(...$this->query2parameters('GET', $_GET)));
        if(ob_get_length()) throw new \Error('方法内部不要echo',500);
        header('Content-Length: '.strlen($ret));
        return $ret;

      case 'OPTIONS':
        return $this->OPTIONS();

      case 'PUT':
      case 'PATCH':
      case 'POST':
      case 'DELETE':
        $ret = static::{$verb}(...$this->query2parameters($verb, $_GET));
        if(ob_get_length()) throw new \Error('方法内部不要echo',500);
        http_response_code($ret?204:202);
        return '';

      case 'TRACE':
      case 'CONNECT':
      default:
        throw new \BadMethodCallException('Not Implemented',501);
      }

    }catch(\Throwable $t){
      http_response_code($t->getCode()?:500);
      $ret = $t->getCode()&&$t->getMessage()?$this->vary(['code'=>$t->getCode(),'reason'=>$t->getMessage()]):'Internal Server Error';
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
          yield new \DateTimeImmutable($value); //FIXME 如果引用 \DateTimeImmutable &$date，怎么处理？
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
