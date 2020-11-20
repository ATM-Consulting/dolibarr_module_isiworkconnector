<?php
/* Copyright (C) 2019 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!class_exists('SeedObject'))
{
	/**
	 * Needed if $form->showLinkedObjectBlock() is call or for session timeout on our module page
	 */
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__).'/../config.php';
}


class Isiworkconnector extends SeedObject
{

    public $Login;
    public $Password;
    public $urlClient;
    Public $baseUrl;
    public $lastXmlResult;

    const  URI_EDIT_END_POINT = "Edit_Source.php?";
    const  URI_PRE_UPLOAD_END_POINT = "Pre_Upload.php?";
    const  URI_UPLOAD_END_POINT = "Upload.php?";
    const  URI_POST_UPLOAD_END_POINT = "Post_Upload.php?";

    const  CONTEXT_SOURCE_ID  = 1;
    const  CONTEXT_PRE_UPLOAD_VALIDATION  = 2;
    const  CONTEXT_UPLOAD  = 3;
    const  CONTEXT_UPLOAD_MYLAB  = 4;


    /**
    * isiworkconnector constructor.
     *
     * @param DoliDB $db
     */
    function __construct(DoliDB &$db) {
        global $conf;
        parent::__construct($db);

    }

    /**
     * @param string $login
     * @param string $password
     * @param string $urlClient
     * @param string $baseUrl
     */
    public function setCredentials($accountCredentials){

        $this->Login        = $accountCredentials->login;
        $this->Password     = $accountCredentials->password;
        $this->urlClient    = $accountCredentials->urlClient;
        $this->baseUrl      = $accountCredentials->baseUrl;
    }

    /**

     */
    public function checkConnection(){
        // -1   /    >  0 SOurceId

        $params = array();
        $params['Coll_Id'] ='coll_1';
        $params['Titre'] = 'Nomsource';
        $result = $this->sendQuery(self::CONTEXT_SOURCE_ID, $params);
        return $result == -1 ? 0 : 1 ;
    }

    /**
     * @return mixed
     */
    public function getLastXmlErrorMsg(){

        var_dump($this->lastXmlResult);exit;
        if (isset($this->lastXmlResult)){
            return (string) $this->lastXmlResult->Error_Msg;
        }
    }

    /**
     * @param string $query
     * @return array
     */
    function parseQuery($query = '')
    {

        $fields = array();

        foreach (explode('&', $query) as $q)
        {
            $q = explode('=', $q, 2);
            if ('' === $q[0]) continue;
            $q = array_map('urldecode', $q);
            $fields[$q[0]][] = isset($q[1]) ? $q[1] : '';
        }

        return $fields;
    }


    /**
     * @param int $context
     * @param mixed $params
     * @return int | SimpleXMLElement | string[]
     */
    public function sendQuery($context,$params){

    	global $langs;
        $baseUrl  = $this->getCustomUri($context,$params);

        try{

            $dataResult = file_get_contents($this->getCustomUri($context,$params) , null);
            // Check the return value of curl_exec(), too

            if ($dataResult === false) {
                setEventMessage($langs->trans('ErrorApiCall'),"errors");
                return 0;

            }else{
	            // Transforme le $content en un objet xml utilisable
                $xml = simplexml_load_string($dataResult);
                $this->lastXmlResult = $xml;

                switch($context){

                    case SELF::CONTEXT_SOURCE_ID:
                        if(isset($xml->Id_Source)){
                            return (int) $xml->Id_Source;
                        }else{
                            return $xml->Result;
                        }

                    case SELF::CONTEXT_PRE_UPLOAD_VALIDATION:

                        if(isset($xml->Result) && $xml->Result == 0 ){
                            $result = ['success'=> "ok",'Upload_Id' => (string) $xml->Upload_Id ];
                            return $result; //46PBolM5XnCThWNzDYUHK7s0k2ZJVfcI
                        }else{
                            $result = ['success'=> "ko",'Error_Msg' => (string) $xml->Error_Msg ];
                            return $result; // upload
                        }

                    case SELF::CONTEXT_UPLOAD:
                        var_dump($dataResult);exit;
                        break;
                }
            }

        }catch(Exception $e) {
            trigger_error(sprintf(
                'call Api failed with error #%d: %s',
                $e->getCode(), $e->getMessage()),
                E_USER_ERROR);
        }



    }

    /**
     * @param int $context
     * @param mixed $params
     * @return string
     */
    public function getCustomUri($context,$params){
        $baseUrl = "";
        switch($context){
            case self::CONTEXT_SOURCE_ID :

                $baseUrl = $this->baseUrl . SELF::URI_EDIT_END_POINT;
                $baseUrl.= 'Login='.$this->Login
                    .'&CPassword='.$this->Password
                    .'&Url_Client='.$this->urlClient
                    .'&Coll_Id='.$params['Coll_Id']
                    .'&Titre='.$params['Titre']
                    .'&Id_Type_Source=5';

                return $baseUrl;


            case self::CONTEXT_PRE_UPLOAD_VALIDATION :

                $baseUrl = $this->baseUrl . SELF::URI_PRE_UPLOAD_END_POINT;
                $baseUrl.= 'Login='.$this->Login
                    .'&CPassword='.$this->Password
                    .'&Url_Client='.$this->urlClient
                    .'&Coll_Id='.$params['Coll_Id']
                    .'&FileName='.$params['fileName']
                    .'&MD5='.$params['md5FileName']
                    .'&Id_Source='.$params['sourceId'];

	            return $baseUrl;

            case self::CONTEXT_UPLOAD :
                $baseUrl = $this->baseUrl . SELF::URI_UPLOAD_END_POINT;
                $baseUrl.= 'Login='.$this->Login
                    .'&CPassword='.$this->Password
                    .'&Url_Client='.$this->urlClient
                    .'&Coll_Id='.$params['Coll_Id'];

                return $baseUrl;
        }

}

    /**
     *
     */
    public function test($credentials,$contextApi,$params){

        $data_json = json_encode($credentials);


        $options = [
            'http' => [
                'protocol_version' => 1.1,
                'method' => "POST",
                'header' => "application/soap+xml",
                'content' => $data_json,
                "timeout" => (float) 10.0
            ],
            'ssl' => [
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];


        /*
         * <?php
			file_get_contents can do a POST, create a context for that first:

			$opts = array('http' =>
			  array(
			    'method'  => 'POST',
			    'header'  => "Content-Type: text/xml\r\n".
			      "Authorization: Basic ".base64_encode("$https_user:$https_password")."\r\n",
			    'content' => $body,
			    'timeout' => 60
			  )
			);

			$context  = stream_context_create($opts);
			$url = 'https://'.$https_server;
			$result = file_get_contents($url, false, $context, -1, 40000);

			?>
         */

        $context = stream_context_create($options);
        var_dump($context);
        var_dump($this->getCustomUri($contextApi,$params));

        $dataResult = file_get_contents($this->getCustomUri($contextApi,$params) , null);
        //$this->http_response_header = self::parseHeaders($http_response_header);

        var_dump($dataResult);exit;
        if($dataResult) {
            $dataResponse = json_decode($dataResult);
            if($dataResponse) {
                return $dataResponse;
            }

        }



    }

}


