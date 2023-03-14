<?php
namespace netgiro\gateway\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;


class Config
{

    const XML_PATH_TEST_MODE = "payment/netgiro/test_mode";
    const XML_PATH_APP_ID = "payment/netgiro/app_id";
    const XML_PATH_SECRET_KEY = "payment/netgiro/secret_key";
    
    private $config;
    public function __construct(ScopeConfigInterface $config){
        $this->config = $config;
    }

    public function getAppId(){
        $testMode = $this->config->getValue(self::XML_PATH_TEST_MODE);
        if($testMode){
            return '881E674F-7891-4C20-AFD8-56FE2624C4B5';
        } else {
            return $this->config->getValue(self::XML_PATH_APP_ID);
        }
    }

    public function getSecretKey(){
        $testMode = $this->config->getValue(self::XML_PATH_TEST_MODE);
        if($testMode){
            return 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A=';
        } else {
            return $this->config->getValue(self::XML_PATH_SECRET_KEY);
        }
    }

    public function getAction(string $action = null)
    {

        $testMode = $this->config->getValue(self::XML_PATH_TEST_MODE);
        if($testMode){
            if($action == 'securepay'){
                return 'https://test.netgiro.is/securepay';
            }
            return 'https://test.netgiro.is/api/' . $action;
        } else {
            if($action == 'securepay'){
                return 'https://securepay.netgiro.is/v1/';
            }
            return 'https://securepay.netgiro.is/v1/api/' . $action;
        }
    }

}
