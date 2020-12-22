<?php declare(strict_types = 1);

namespace netgiro\gateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ScopeInterface;


class Data extends AbstractHelper
{

	public function getConfig($config_path)
	{
		return $this->scopeConfig->getValue(
			$config_path,
			ScopeInterface::SCOPE_DEFAULT
		);
	}

}