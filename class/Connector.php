<?php

abstract class Connector extends SeedObject {
    public $login;
    public $password;

    /**
     * ConnectorInterface constructor
     *
     * @param DoliDB $db
     */
    public function __construct(DoliDB &$db) { parent::__construct($db); }

    /**
     * @param stdClass $accountCredentials
     * @return mixed
     */
    abstract public function setCredentials($accountCredentials);

    /**
     * @param int   $context
     * @param mixed $params
     * @return mixed
     */
    abstract public function sendQuery($context, $params);

    /**
     * @param int   $context
     * @param mixed $params
     * @return mixed
     */
    abstract function getCustomUri($context, $params);

    /**
     * @param string $message
     * @param string $logSuffix
     * @param int    $logLevel
     */
    protected static function logMeThis($message, $logSuffix, $logLevel = LOG_ERR): void {
        try {
            dol_syslog($message, $logLevel, 0, $logSuffix);
        }
        catch(Exception $e) {}
    }
}
