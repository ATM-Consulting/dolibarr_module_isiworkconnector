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
require_once __DIR__.'/Connector.php';

if (!class_exists('SeedObject'))
{
    /**
     * Needed if $form->showLinkedObjectBlock() is call or for session timeout on our module page
     */
    define('INC_FROM_DOLIBARR', true);
    require_once dirname(__FILE__).'/../config.php';
}


class ZennDocConnector extends Connector
{


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
    const  CONTEXT_POST_UPLOAD  = 4;


    /**
     * isiworkconnector constructor.
     *
     * @param DoliDB $db
     */
    function __construct(DoliDB &$db) {

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
        $this->urlClient    = $accountCredentials->urlclient;
        $this->baseUrl      = $accountCredentials->baseUrl;
    }

    /**
     */
    public function checkConnection(){
        // -1   /    >  0 SourceId

        /**
         * il n'y a pas d'autr emoyen de savoir si les login / password sont correct que de tenter de récuperer
         * une sourceId
         * dans le cas d'une problême la fonction renvoie -1 sinon un int signed
         */
        $params = array();
        //@TODO PLACE COLL_id et nomSource en conf
        $params['Coll_Id'] ='coll_1';
        $params['Titre'] = 'Nomsource';
        $result = $this->sendQuery(self::CONTEXT_SOURCE_ID, $params);
        return $result == -1 ? 0 : 1 ;
    }

    /**
     * @return mixed
     */
    public function getLastXmlErrorMsg(){

        if (isset($this->lastXmlResult)){
            return (string) $this->lastXmlResult->Error_Msg;
        }
    }


    /**
     * @param  int $context
     * @param  mixed $params
     * @return int | SimpleXMLElement | string[]
     */
    public function sendQuery($context,$params){

        global $langs;
        // Construit l'url correcte selon le contexte donné en paramètre
        $baseUrl  = $this->getCustomUri($context,$params);

        $dataResult = file_get_contents($baseUrl, false);
        // Transforme le $dataResult en un objet xml utilisable
        $xml = simplexml_load_string($dataResult);
        // Pour afficher les erreurs eventuelles depuis le cron
        $this->lastXmlResult = $xml;

        try {

            switch ($context) {

                case self::CONTEXT_SOURCE_ID :
                    if (isset($xml->Id_Source)) {
                        return (int)$xml->Id_Source;
                    } else {
                        return $xml->Result;
                    }
                    break;

                case self::CONTEXT_PRE_UPLOAD_VALIDATION :

                    if (isset($xml->Result) && $xml->Result == 0) {
                        $result = ['success' => "ok", 'Upload_Id' => (string)$xml->Upload_Id];
                        return $result;
                    } else {
                        $result = ['success' => "ko", 'Error_Msg' => (string)$xml->Error_Msg];
                        return $result;
                    }
                    break;

                case self::CONTEXT_UPLOAD :

                    define('MULTIPART_BOUNDARY', '--------------------------'.microtime(true));

                    $header = 'Content-Type: multipart/form-data; boundary='.MULTIPART_BOUNDARY;
                    // equivalent to <input type="file" name="uploaded_file"/>
                    define('FORM_FIELD', 'Upload_File');

                    $filename = $params['path']."/".$params['fileName'];
                    $filepath = DOL_DATA_ROOT."/".$filename;
                    $file_content_to_upload = file_get_contents($filepath, true);

                    $ext = substr(strrchr($filename, '.'), 0);

                    $content =  "--".MULTIPART_BOUNDARY."\r\n".
                        "Content-Disposition: form-data; name=\"".FORM_FIELD."\"; filename=\"".$params['upload_id'].$ext."\"\r\n".
                        "Content-Type: ".mime_content_type($filepath)."\r\n\r\n".
                        $file_content_to_upload."\r\n";

                    // signal end of request
                    $content .= "--".MULTIPART_BOUNDARY."--\r\n";

                    $contextStream = stream_context_create(array(
                        'http' => array(
                            'method' => 'POST',
                            'header' => $header,
                            'content' => $content,
                        )
                    ));

                    $dataResultUpload = file_get_contents($baseUrl, false, $contextStream);
                    $xml = simplexml_load_string($dataResultUpload);

                    if (isset($xml->Result) && $xml->Result == 0) {
                        $result = ['success' => "ok"];
                        return $result;
                    } else {
                        $result = ['success' => "ko", 'Error_Msg' => (string)$xml->Error_Msg];
                        return $result;
                    }
                    // END case self::CONTEXT_UPLOAD
                    break;

                case self::CONTEXT_POST_UPLOAD :

                    if (isset($xml->Result) && $xml->Result == 0) {
                        $result = ['success' => "ok"];
                        return $result;
                    } else {
                        $result = ['success' => "ko", 'Error_Msg' => (string)$xml->Error_Msg];
                        return $result;
                    }
                    // END case self::CONTEXT_POST_UPLOAD
                    break;
            }

            if ($dataResult === false) {
                setEventMessage($langs->trans('ErrorApiCall'),"errors");
                return 0;
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

                $baseUrl = $this->baseUrl ."/". self::URI_EDIT_END_POINT;
                $baseUrl.= 'Login='.$this->Login
                    .'&CPassword='.$this->Password
                    .'&Url_Client='.$this->urlClient
                    .'&Coll_Id='.$params['Coll_Id']
                    .'&Titre='.$params['Titre']
                    .'&Id_Type_Source=5';

                return $baseUrl;

            case self::CONTEXT_PRE_UPLOAD_VALIDATION :

                $baseUrl = $this->baseUrl ."/". self::URI_PRE_UPLOAD_END_POINT;
                $baseUrl.= 'Login='.$this->Login
                    .'&CPassword='.$this->Password
                    .'&Url_Client='.$this->urlClient
                    .'&Coll_Id='.$params['Coll_Id']
                    .'&FileName='.$params['fileName']
                    .'&MD5='.md5_file(DOL_DATA_ROOT."/".$params['path']."/".$params['fileName'])
                    .'&Id_Source='.$params['sourceId'];

                return $baseUrl;

            case self::CONTEXT_UPLOAD :

                $baseUrl = $this->baseUrl ."/". self::URI_UPLOAD_END_POINT;
                $baseUrl.= 'Login='.$this->Login
                    .'&CPassword='.$this->Password
                    .'&Url_Client='.$this->urlClient
                    .'&Coll_Id='.$params['Coll_Id'];

                return $baseUrl;


            case self::CONTEXT_POST_UPLOAD :

                $baseUrl = $this->baseUrl ."/". self::URI_POST_UPLOAD_END_POINT;
                $baseUrl.= 'Login='.$this->Login
                    .'&CPassword='.$this->Password
                    .'&Url_Client='.$this->urlClient
                    .'&Coll_Id='.$params['Coll_Id']
                    .'&Upload_Id='.$params['upload_id'];

                return $baseUrl;
        }

    }



}
