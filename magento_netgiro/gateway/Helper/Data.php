<?php declare(strict_types = 1);

namespace netgiro\gateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{

    public const MODULE_NAME = 'netgiro_gateway';

    /**
     * Class description goes here
     *
     * @var ModuleListInterface $moduleList
     */
    protected $_moduleList;

    /**
     * Class constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     */
    public function __construct(
        Context $context,
        ModuleListInterface $moduleList
    ) {
        $this->_moduleList = $moduleList;
        parent::__construct($context);
    }

    /**
     * Get configuration value.
     *
     * @param string $config_path
     * @return mixed
     */
    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            ScopeInterface::SCOPE_DEFAULT
        );
    }

    /**
     * Get module version.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->_moduleList
            ->getOne(self::MODULE_NAME)['setup_version'];
    }
}
