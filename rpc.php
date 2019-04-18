<?php namespace srv; // vim: se fdm=marker:

abstract class rpc extends api{

  final function GET():string{
    header('Content-Type: application/xml;charset=UTF-8');
    return $this->wsdl();
  }

  abstract function POST();

  /**
   * @description 根据__debugInfo()文档，生成wsdl文档
   * @todo 排除原生方法之后，剩下的public方法一律暴露
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

}
