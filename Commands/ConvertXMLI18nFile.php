<?php
namespace Rbs\Janitor\Commands;

use Change\Events\EventManagerFactory;
use Change\Plugins\Plugin;
use Change\Services\ApplicationServices;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Zend\Json\Json;

/**
 * @name \Rbs\Janitor\Commands\ConvertXMLI18File
 */
class ConvertXMLI18nFile extends Command
{
	protected function configure()
	{
		$this->setName('rbs_janitor:convert-i18n')
			->addArgument('path', InputArgument::REQUIRED, 'path');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->load($input->getArgument('path'));
		$data = [];
		foreach ($dom->getElementsByTagName('key') as $keyElement)
		{
			$value = $this->fixSubstitutions($keyElement->textContent);
			$key = str_replace('-', '_', $keyElement->getAttribute('id'));
			$data[$key] = [
				'message' => $value
			];
		}
		echo \Zend\Json\Json::prettyPrint(json_encode($data, JSON_UNESCAPED_UNICODE));
	}

	protected function fixSubstitutions($textContent)
	{
		return preg_replace_callback('/\{.*?\}/', function($matches){return mb_strtoupper(str_replace(['{', '}'], '$', $matches[0]));}, $textContent);
	}
} 