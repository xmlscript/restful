<?php namespace srv; // vim: se fdm=marker:

class soap extends api{

  /**
   * @description 一个GET要担负多种角色，可能返回: WSDL, disco, index, 甚至http绑定get
   * @param $wsdl 显示xml格式的WSDL文档
   * @param $op 显示具体方法的form表单
   * @param $disco 被index.html的link到一个发现服务文档
   * @param 都不填，显示所有方法的index.html，可以带op参数内链到具体方法的form
   * @todo restful也可以借鉴或复用
   */
  function GET(string $op=null){
    header('content-type: text/plain');
    //header('Content-Type: application/xml;charset=UTF-8');//正确生成，才输出xml头

    if(isset($_GET['wsdl'])){
      return '<wsdl>WSDL</wsdl>';
    }elseif(isset($_GET['disco'])){
      return '<disco>DISCO</disco>';
    }else switch($op){
      case 'hi':
      case 'hey':
        break;
    }

    return;
    var_dump($_GET);
    var_dump($wsdl);
    var_dump(static::class);
    return '';
  }


  /**
   * @description 反射index.html
   */
  final private function index(){#{{{
    return '<link rel=alternate type=application/xml href=?disco>';
    return '<link rel=alternate type=application/xml href=?WSDL>';
  }#}}}


  /**
   * @description 反射具体方法的html
   */
  final private function op(string $method){#{{{

  }#}}}


  /**
   * @description 反射public方法生成wsdl文档
   */
  final private function wsdl(){#{{{
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


  /**
   * @description 发现服务
   */
  final private function disco(){#{{{
    /**
     * <discovery><contractRef ref="http://host/soap?wsdl" docRef="http://host/soap"/>
     *   <soap address="http://host/soap" binding="q1:WeatherWebServiceSoap"/>
     *   <soap address="http://host/soap" binding="q2:WeatherWebServiceSoap12"/>
     * </discovery>
     */
  }#}}}


  /**
   * @todo ASP.net可以用html表单直接post，可能是因为使用了<binding>
   */
  final function POST(){

    $soap = new \SoapServer(self::GET(),[
      'uri' => 'xx', //nonWSDL模式必须有uri，但是可以随意设置，哪怕empty字符串
    ]);

    $soap->setClass(static::class); //FIXME static::class 导致500异常fault
    $soap->handle(); //调用private将产生三条log

    die;//FIXME 后续的请求头控制一律忽略掉了

    return $soap; //TODO null就一律忽略__invoke()后续操作

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
