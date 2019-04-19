<?php namespace srv; // vim: se fdm=marker:

class soap extends rpc{

  /**
   * @description 根据__debugInfo()文档，生成wsdl文档
   * @todo 排除原生方法之后，剩下的public方法一律暴露
   */
  final function wsdl():string{#{{{
    //header('Content-Type: text/plain');
    //var_dump($this->__debugInfo());die;
    return '<definitions></definitions>';
    /**典型的WSDL文件
     * <?xml version="1.0" encoding="UTF-8"?>
     * <definitions name="MobilePhoneService"
     *     targetNamespace="www.mobilephoneservice.com/MobilePhoneService-interface"
     *     xmlns="http://schemas.xmlsoap.org/wsdl/"
     *     xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
     *     xmlns:tns="http://www.mobilephoneservice.com/MobilePhoneService"
     *     xmlns:xsd="http://www.w3.org/1999/XMLSchema">
     *   <documentation>反射class描述</documentation>
     *
     *   <types>
     *     <s:schema elementFromDefault="sldfj" targetNamespace="sdllsdfldsf">
     *
     *       <s:element name="method1">
     *         <s:complexType>
     *           <s:sequence>
     *             <s:element minOccurs="0" maxOccurs="1" name="arg1" type="s:string"/>
     *           </s:sequence>
     *         </s:complexType>
     *       </s:element>
     *
     *       <s:complexType name="ArrayOfString"></s:complexType>
     *       <s:element name="ArrayOfString" nillable="true" type="tns:ArrayOfString"/>
     *
     *     </s:schema>
     *   </types>
     *
     *   <message name="XXX">
     *     <part name="arg1" type="s:string"/>
     *   </message>
     *
     *   <message name="FIXME">
     *     <part name="Body" element="tns:AAAA"/>
     *   </message>
     *
     *   <portType name="cls1">
     *     <operation name="cls1.method1">
     *       <documentation>反射该方法的描述</documentation>
     *       <input message="tns:XXX"/>
     *       <output message="tns:ArrayOfString"/>
     *     .......
     *     </operation>
     *   </portType>
     *
     *   <binding name="cls1" type="tns:cls1">
     *     <soap:binding transport="http://schemas.xmlsoap.org/soap/http"/>
     *     <operation name="method1">
     *       <soap:operation soapAction="http://host/soap" style="document"/>
     *       <input>
     *        <body use="literal"/>
     *       </input>
     *     </operation>
     *   </binding>
     *
     *   <binding name="cls2" type="tns:cls2">
     *     <http:binding verb="GET"/>
     *     <operation name="method2">
     *       <soap:operation location="/xxx"/>
     *       <input>
     *        <http:urlEncoded/>
     *       </input>
     *       <output>
     *        <mime:mimeXml part="Body"/>
     *       </output>
     *     </operation>
     *   </binding>
     *
     *   <service name="cls1">
     *     <documentation>重复了class描述</documentation>
     *     <port name="method1" binding="tns:method1">
     *       <soap:address location="http://host/soap"/>
     *     </port>
     *   </service>
     *
     * </definitions>
     */
  }#}}}


  final function GET():string{
    header('Content-Type: application/xml;charset=UTF-8');
    return $this->wsdl();
  }


  final function POST():void{

    try{
      //$soap = new \SoapServer($this->wsdl(),[
      $soap = new \SoapServer(null,[
        'uri' => $_SERVER['REQUEST_URI'], //FIXME nonWSDL模式必须有uri，但是可以随意设置，哪怕empty字符串
        'soap_version' => SOAP_1_2, //FIXME 作用不明
        'send_errors' => true, //FIXME 意义不明
      ]);

      $soap->setClass(static::class);
      $soap->handle(); //调用private将产生三条log；如果call里有Exception，则内部die，无法执行后续操作（但Error可以被转换成SoapFault）
    }catch(\Throwable $t){ //FIXME 理论上，客户端设置exceptions=true，就应该识别异常，但实际无效
      $soap->fault($t->getCode(),$t->getMessage());
    }

  }

}
