<?php namespace srv; // vim: se fdm=marker:

class soap extends api{

  final function GET():string{
    header('Content-Type: application/xml;charset=UTF-8');
    return $this->wsdl();
  }


  /**
   * @description 反射public方法生成wsdl文档
   * @todo 能否复用__debugInfo()，排除原生方法之后，剩下的public方法一律暴露
   */
  final private function wsdl():string{#{{{
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


  final function POST():void{

    //$soap = new \SoapServer($this->wsdl(),[
    $soap = new \SoapServer(null,[
      'uri' => 'xx', //FIXME nonWSDL模式必须有uri，但是可以随意设置，哪怕empty字符串
    ]);

    $soap->setClass(static::class);
    $soap->handle(); //调用private将产生三条log

    die;//FIXME 后续的请求头控制一律忽略掉了

    //return $soap; //TODO null就一律忽略__invoke()后续操作

    /** SOAP客户端发来的请求
     * <?xml version="1.0" encoding="UTF-8"?>
     *   <SOAP-ENV:Envelope
     *       xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
     *       xmlns:ns1="xxx"
     *       xmlns:xsd="http://www.w3.org/2001/XMLSchema"
     *       xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
     *       SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
     *     <SOAP-ENV:Body>
     *       <ns1:hey/>
     *     </SOAP-ENV:Body>
     * </SOAP-ENV:Envelope>
     */

    /** SOAP服务端的正确响应
     * <?xml version="1.0" encoding="UTF-8"?>
     * <SOAP-ENV:Envelope
     *     xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
     *     xmlns:ns1="xxx"
     *     xmlns:xsd="http://www.w3.org/2001/XMLSchema"
     *     xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
     *     xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
     *     SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
     *   <SOAP-ENV:Body>
     *     <ns1:hiResponse>
     *       <return xsi:type="xsd:string">hiiiiiiiiiiiiiiiiiiiiiiiii</return>
     *     </ns1:hiResponse>
     *   </SOAP-ENV:Body>
     * </SOAP-ENV:Envelope>
     */

    /** SOAP服务端返回的错误消息
     * <?xml version="1.0" encoding="UTF-8"?>
     * <SOAP-ENV:Envelope
     * xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
     *   <SOAP-ENV:Body>
     *     <SOAP-ENV:Fault>
     *       <faultcode>SOAP-ENV:Server</faultcode>
     *       <faultstring>Return value of class@anonymous::hi() must be of the type array, string returned</faultstring>
     *     </SOAP-ENV:Fault>
     *   </SOAP-ENV:Body>
     * </SOAP-ENV:Envelope>
     */

  }

}
