<?php declare(strict_types = 1);

namespace netgiro\gateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{

	const MODULE_NAME = 'netgiro_gateway';

	/**
	 * @param \Magento\Framework\Module\ModuleListInterface $moduleList
	 */
	protected $_moduleList;

	public function __construct(
		Context $context,
		ModuleListInterface $moduleList)
	{
		$this->_moduleList = $moduleList;
		parent::__construct($context);
	}

	public function getConfig($config_path)
	{
		return $this->scopeConfig->getValue(
			$config_path,
			ScopeInterface::SCOPE_DEFAULT
		);
	}

	public function getVersion()
	{
		return $this->_moduleList
			->getOne(self::MODULE_NAME)['setup_version'];
	}

}