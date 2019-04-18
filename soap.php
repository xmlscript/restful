<?php namespace srv; // vim: se fdm=marker:

class soap extends rpc{

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
