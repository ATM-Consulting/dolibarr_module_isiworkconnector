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
	 * Needed if $form->showLinkedObjectBlock() is call or for session
	timeout on our module page
	 */
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__).'/../config.php';
}


class ZeenDocConnector extends Connector {
    public $urlClient;
    public $baseUrl;
    public $lastXmlResult;
    public $classeur;
    public $idsource;

    public $errors = '';
    public $output = '';
    public $fileUploaded = 0;
    public $errorFileUpload = 0;

	const URI_EDIT_END_POINT = 'Edit_Source.php?';
	const URI_PRE_UPLOAD_END_POINT = 'Pre_Upload.php?';
	const URI_UPLOAD_END_POINT = 'Upload.php?';
	const URI_POST_UPLOAD_END_POINT = 'Post_Upload.php?';

	const ZEENDOC_STATUS_FILE_DELETED = -1;
	const ZEENDOC_STATUS_FILE_INDEXED = 1;
	const ZEENDOC_STATUS_FILE_TO_INDEX = 2;
	const ZEENDOC_STATUS_FILE_PROTECTED = 3;

	const CONTEXT_SOURCE_ID = 1;
	const CONTEXT_PRE_UPLOAD_VALIDATION = 2;
	const CONTEXT_UPLOAD = 3;
	const CONTEXT_POST_UPLOAD = 4;

    /**
     * isiworkconnector constructor.
     *
     * @param DoliDB   $db
     * @param stdClass $accountCredentials
     */
    function __construct(DoliDB &$db, $accountCredentials) {
        parent::__construct($db);
        $this->setCredentials($accountCredentials);
    }

    /**
     * @param stdClass $accountCredentials
     */
    public function setCredentials($accountCredentials) {
        $this->login = $accountCredentials->login;
        $this->password = $accountCredentials->password;
        $this->urlClient = $accountCredentials->urlclient;
        $this->baseUrl = $accountCredentials->baseUrl;
        $this->classeur = $accountCredentials->classeur;
        $this->idsource = $accountCredentials->idsource;
    }

    /**
     * @return mixed
     */
    public function getLastXmlErrorMsg() {
        if(isset($this->lastXmlResult)) {
            return (string) $this->lastXmlResult->Error_Msg;
        }

        return '';
    }

    /**
     * @param int   $context
     * @param mixed $params
     * @return int|SimpleXMLElement|string[]
     */
    public function sendQuery($context, $params) {
        global $langs;

        // Construit l'url correcte selon le contexte donné en paramètre
        $baseUrl = $this->getCustomUri($context, $params);

        $dataResult = file_get_contents($baseUrl, false);
        // Transforme le $dataResult en un objet xml utilisable
        $xml = simplexml_load_string($dataResult);
        // Pour afficher les erreurs eventuelles depuis le cron
        $this->lastXmlResult = $xml;

        try {
            switch($context) {
                case self::CONTEXT_SOURCE_ID:
                    if(isset($xml->Id_Source)) {
                        return intval($xml->Id_Source);
                    }
                    else {
                        return $xml->Result;
                    }
                    break;
                case self::CONTEXT_PRE_UPLOAD_VALIDATION:
                    if(isset($xml->Result) && $xml->Result == 0) {
                        return [
                            'success' => "ok",
                            'Upload_Id' => (string) $xml->Upload_Id
                        ];
                    }
                    else {
                        return [
                            'success' => "ko",
                            'Error_Msg' => (string) $xml->Error_Msg
                        ];
                    }
                    break;
                case self::CONTEXT_UPLOAD:
                    define('MULTIPART_BOUNDARY', '--------------------------'.microtime(true));

                    $header = 'Content-Type: multipart/form-data; boundary='.MULTIPART_BOUNDARY;
                    // equivalent to <input type="file" name="uploaded_file"/>
                    define('FORM_FIELD', 'Upload_File');

                    $filename = $params['path'].'/'.$params['fileName'];
                    $filepath = DOL_DATA_ROOT.'/'.$filename;
                    $file_content_to_upload = file_get_contents($filepath, true);

                    $ext = substr(strrchr($filename, '.'), 0);

                    $content = '--'.MULTIPART_BOUNDARY."\r\n";
                    $content .= 'Content-Disposition: form-data; name="'.FORM_FIELD.'"; filename="'.$params['upload_id'].$ext.'"'."\r\n";
                    $content .= 'Content-Type: '.mime_content_type($filepath)."\r\n\r\n".$file_content_to_upload."\r\n";

                    // signal end of request
                    $content .= '--'.MULTIPART_BOUNDARY."--\r\n";

                    $contextStream = stream_context_create([
                        'http' => [
                            'method' => 'POST',
                            'header' => $header,
                            'content' => $content,
                        ]
                    ]);

                    $dataResultUpload = file_get_contents($baseUrl, false, $contextStream);
                    $xml = simplexml_load_string($dataResultUpload);

                    if(isset($xml->Result) && $xml->Result == 0) {
                        return ['success' => 'ok'];
                    }
                    else {
                        return ['success' => 'ko', 'Error_Msg' => (string) $xml->Error_Msg];
                    }
                    break;
                case self::CONTEXT_POST_UPLOAD:
                    if(isset($xml->Result) && $xml->Result == 0) {
                        return ['success' => 'ok'];
                    }
                    else {
                        return ['success' => 'ko', 'Error_Msg' => (string) $xml->Error_Msg];
                    }
                    break;
            }

            if($dataResult === false) {
                setEventMessage($langs->trans('ErrorApiCall'), 'errors');
                return 0;
            }
        }
        catch(Exception $e) {
            trigger_error(sprintf('call Api failed with error #%d: %s', $e->getCode(), $e->getMessage()), E_USER_ERROR);
        }

        return -1;
    }

    /**
     * @param int   $context
     * @param mixed $params
     * @return string
     */
    public function getCustomUri($context, $params) {
        $baseUrl = "";

        switch($context) {
            case self::CONTEXT_SOURCE_ID:
                $baseUrl = $this->baseUrl.'/'.self::URI_EDIT_END_POINT;
                $baseUrl .= 'Login='.$this->login;
                $baseUrl .= '&CPassword='.$this->password;
                $baseUrl .= '&Url_Client='.$this->urlClient;
                $baseUrl .= '&Coll_Id='.$params['Coll_Id'];
                $baseUrl .= '&Titre='.$params['Titre'];
                $baseUrl .= '&Id_Type_Source=5';
                break;
            case self::CONTEXT_PRE_UPLOAD_VALIDATION:
                $baseUrl = $this->baseUrl.'/'.self::URI_PRE_UPLOAD_END_POINT;
                $baseUrl .= 'Login='.$this->login;
                $baseUrl .= '&CPassword='.$this->password;
                $baseUrl .= '&Url_Client='.$this->urlClient;
                $baseUrl .= '&Coll_Id='.$params['Coll_Id'];
                $baseUrl .= '&FileName='.urlencode($params['fileName']);
                $baseUrl .= '&MD5='.md5_file(DOL_DATA_ROOT."/".$params['path']."/".$params['fileName']);
                $baseUrl .= '&Id_Source='.$params['sourceId'];

                if(! empty($params['CustomClassement'])) {
                    foreach($params['CustomClassement'] as $key => $custom) {
                        $baseUrl .= '&'.$key.'='.$custom;
                    }
                }
                break;
            case self::CONTEXT_UPLOAD:
                $baseUrl = $this->baseUrl.'/'.self::URI_UPLOAD_END_POINT;
                $baseUrl .= 'Login='.$this->login;
                $baseUrl .= '&CPassword='.$this->password;
                $baseUrl .= '&Url_Client='.$this->urlClient;
                $baseUrl .= '&Coll_Id='.$params['Coll_Id'];
                break;
            case self::CONTEXT_POST_UPLOAD:
                $baseUrl = $this->baseUrl.'/'.self::URI_POST_UPLOAD_END_POINT;
                $baseUrl .= 'Login='.$this->login;
                $baseUrl .= '&CPassword='.$this->password;
                $baseUrl .= '&Url_Client='.$this->urlClient;
                $baseUrl .= '&Coll_Id='.$params['Coll_Id'];
                $baseUrl .= '&Upload_Id='.$params['upload_id'];
        }

        return $baseUrl;
    }

    /**
     * @param string $alreadySentFile
     * @return array|bool|mixed|object
     * @throws SoapFault
     */
    public function fetchfile($alreadySentFile) {
        global $langs;

        // Soap Call Preparation
        $wsdl = 'https://armoires.zeendoc.com/'.$this->urlClient.'/ws/1_0/wsdl.php?WSDL';

		$client = new SoapClient($wsdl);

        // On transforme le retour SOAP en objet
        $rights = json_decode($client->__soapCall('getRights', [
                new SoapParam($this->login, 'Login'),
                new SoapParam('', 'Password'),
                new SoapParam($this->password, 'CPassword'),
            ]));

        // Si nous avons une Connexion
        if($rights->Result == 0) {
            $complextTypeIndex1 = [
                'Id' => 1,
                'Label' => 'Upload_Id',
                'Value' => $alreadySentFile->upload_id_zeendoc,
                'Operator' => 'EQUALS',
            ];

            $IndexListAsSoapVarObject = new SoapVar([new SoapVar($complextTypeIndex1, SOAP_ENC_OBJECT, 'IndexDefinition', null, 'Index')], SOAP_ENC_OBJECT, 'ArrayOfIndexDefinition', null, 'IndexList');

            // Récupération des infos sur un document stocké chez ZeenDoc
            return json_decode($client->__soapCall('searchDoc', [
                    new SoapParam($this->login, 'Login'),
                    new SoapParam('', 'Password'),
                    new SoapParam($this->password, 'CPassword'),
                    new SoapParam($this->classeur, 'Coll_Id'),
                    $IndexListAsSoapVarObject,
                    new SoapParam(0, 'StrictMode'),
                    new SoapParam('', 'Fuzzy'),
                    new SoapParam('', 'Order_Col'),
                    new SoapParam('', 'Order'),
                    new SoapParam('', 'saved_query'),
                    new SoapParam('', 'Query_Operator'),
                ]));
        }
        else {
            $this->output .= $langs->trans("WrongCredentialsZeendoc");
            $this->errors++;
        }
    }

    /**
     * @param string $fileToSend
     * @return mixed|SimpleXMLElement|string
     */
    public function send($fileToSend) {
        $params = [
            'fileName' => $fileToSend->filename,
            'path' => $fileToSend->filepath,
            'sourceId' => $this->idsource,
            'Coll_Id' => $this->classeur,
            'Titre' => 'Nomsource'
        ];

        $result = $this->sendQuery(self::CONTEXT_PRE_UPLOAD_VALIDATION, $params);

        if($result['success'] == 'ok') {
            // Upload du fichier
            $params['upload_id'] = $result['Upload_Id'];
            $resultUpload = $this->sendQuery(self::CONTEXT_UPLOAD, $params);

            if($resultUpload['success'] == 'ok') {
                // Check post-upload
                $resultPostUpload = $this->sendQuery(self::CONTEXT_POST_UPLOAD, $params);

                if($resultPostUpload['success'] == 'ok') {
                    $this->fileUploaded++;
                    return $result['Upload_Id'];
                }
                else {
                    $this->errorFileUpload++;
                }
            }
            else {
                // errors upload
                $this->output .= $this->getLastXmlErrorMsg();
                $this->errors++;
            }
        }
        else {
            // errors pre-upload
            $this->output = $this->getLastXmlErrorMsg();
            $this->errors++;
        }
    }
}
