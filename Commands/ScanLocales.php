<?php
namespace Rbs\Janitor\Commands;

use Change\Application\ApplicationServices;
use Change\Commands\Events\Event;
use Change\Plugins\Plugin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * @name \Rbs\Janitor\Commands\FindUnusedLocales
 */
class ScanLocales extends Command
{
	/**
	 *
	 */
	protected function configure()
	{
		$this->setName('rbs_janitor:scan-locales')
			->addArgument('path', InputArgument::OPTIONAL, 'path')
			->addOption('unused', null, InputOption::VALUE_NONE, 'unused', null)
			->addOption('find-usage', null, InputOption::VALUE_REQUIRED, 'find usage', null)
			->addOption('undeclared', null, InputOption::VALUE_NONE, 'undeclared', null)
			->addOption('generate', null, InputOption::VALUE_REQUIRED, 'generate undeclared in the given locale (only works with --undeclared)', null);
	}


	/**
	 * @param Event $event
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$application = $this->getApplication()->getChangeApplication(); //->getApplication();
		$path = $input->getArgument('path');

		if ($path === null)
		{
			$path = $application->getWorkspace()->projectPath();
		}
		$finder = new Finder();
		$finder->ignoreVCS(true)->ignoreDotFiles(true)->ignoreUnreadableDirs(true);
		$finder->in($path);
		$finder->depth('> 0');
		$finder->exclude(array('tmp', 'App', 'Compilation', 'Libraries', 'ChangeTests', $application->getConfiguration('Change/Install/webBaseDirectory')));
		$finder->files()/*->name('*.php')*/->name('*.js')/*->name('*.json')->name('*.twig')*/;
		$usedExplicitely = array();
		foreach ($finder as $file)
		{
			/** @var $file \SplFileInfo */
			$content = $file->getContents();
			preg_match_all('/(?<=[\'"])(?:c|m|t)\.[a-z0-9]+(?:\.[a-z0-9-]+)+/i', $content, $keys);
			foreach ($keys[0] as $match)
			{
				$parts = explode('.', strtolower($match));
				if ($parts[0] == 'c' || count($parts) > 3)
				{
					$usedExplicitely[strtolower($match)][] = $file->getPathname();
				}
			}
		}
		foreach ($usedExplicitely as $key => $files)
		{
			$usedExplicitely[$key] = array_unique($files);;
		}

		$as = new ApplicationServices($application);

		$defined = array();
		foreach ($as->getPluginManager()->getPlugins() as $plugin)
		{
			$absPath = $plugin->getAbsolutePath($application->getWorkspace());
			$finder = new Finder();
			$finder->ignoreVCS(true)->ignoreDotFiles(true)->ignoreUnreadableDirs(true);
			$finder->in($absPath);
			$finder->files()->name('fr_FR.xml');
			foreach ($finder as $file)
			{
				$pathParts = [];
				/** @var $file \SplFileInfo */
				$relativePath = trim(str_replace($absPath .'/I18n/Assets', '', $file->getPath()), '/');
				if ($plugin->getType() === Plugin::TYPE_MODULE)
				{
					$pathParts[] = 'm';
				}
				else
				{
					$pathParts[] = 't';
				}
				$pathParts[] = strtolower($plugin->getVendor());
				$pathParts[] = strtolower($plugin->getShortName());
				if (!empty($relativePath))
				{
					$pathParts = array_merge($pathParts, explode(DIRECTORY_SEPARATOR, $relativePath));
				}
				foreach ($as->getI18nManager()->getDefinitionKeys('fr_FR', $pathParts) as $defKey)
				{
					$defined[implode('.', $pathParts) . '.' . $defKey->getId()] = true;
				}
			}
		}

		$absPath = $application->getWorkspace()->changePath();
		$finder = new Finder();
		$finder->ignoreVCS(true)->ignoreDotFiles(true)->ignoreUnreadableDirs(true);
		$finder->in($absPath);
		$finder->files()->name('fr_FR.xml');
		foreach ($finder as $file)
		{
			$pathParts = ['c'];
			/** @var $file \SplFileInfo */
			$relativePath = trim(str_replace($absPath .'/I18n/Assets', '', $file->getPath()), '/');
			if (!empty($relativePath))
			{
				$pathParts = array_merge($pathParts, explode(DIRECTORY_SEPARATOR, $relativePath));
			}
			foreach ($as->getI18nManager()->getDefinitionKeys('fr_FR', $pathParts) as $defKey)
			{
				$defined[implode('.', $pathParts) . '.' . $defKey->getId()] = true;

			}
		}

		$ds = new \Change\Documents\DocumentServices($as);

		$implicitKeys = [];
		foreach ($ds->getModelManager()->getModelsNames() as $modelName)
		{
			$model = $ds->getModelManager()->getModelByName($modelName);
			$implicitKeys[strtolower(implode('.', ['m', $model->getVendorName(), $model->getShortModuleName(), 'documents', $model->getShortName()]))] = true;
			foreach ($model->getPropertyNames() as $pName)
			{
				$implicitKeys[strtolower(implode('.', ['m', $model->getVendorName(), $model->getShortModuleName(), 'documents', $model->getShortName(), $pName]))] = true;
			}
		}

		if ($input->getOption('unused'))
		{
			foreach ($defined as $key => $val)
			{
				$parts = explode('.', $key);
				$isDocLocale = ($parts[0] = 'm' && isset($parts['3']) && $parts['3'] === 'documents');
				if (!$isDocLocale && !isset($usedExplicitely[$key]))
				{
					$output->writeln('<comment>' . $key . '</comment>');
				}
			}
		}

		if ($input->getOption('undeclared'))
		{
			$undeclared = [];
			foreach ($usedExplicitely as $key => $val)
			{
				if (!isset($defined[$key]))
				{
					$undeclared[] = $key;
					$output->writeln('<info>' . $key . '</info>');
					foreach ($val as $file)
					{
						$output->writeln('<comment>' . $file . '</comment>');
					}
				}
			}
			if ($input->getOption('generate'))
			{
				$packageToGenerateOrUpdate = [];
				foreach ($undeclared as $key)
				{
					$parts = explode('.', $key);
					$id = array_pop($parts);
					$package = implode('.', $parts);
					$packageToGenerateOrUpdate[$package][] = $id;
				}
				$dialog = $this->getHelperSet()->get('dialog');
				foreach ($packageToGenerateOrUpdate as $package => $ids)
				{
					if ( $dialog->askConfirmation($output, '<question>Generate or update package ' .  $package . ' y/[n] ? </question>', false))
					{
						$locale = $input->getOption('generate');
						$dir =  $as->getI18nManager()->getCollectionPath($package);
						@mkdir($dir, 0777, true);
						$localeFile = $dir . DIRECTORY_SEPARATOR . $locale . '.xml';
						$dom = new \DOMDocument('1.0', 'UTF-8');
						$dom->preserveWhiteSpace = false;
						$dom->formatOutput = true;

						if (file_exists($localeFile))
						{
							$dom->load($localeFile);
							$root = $dom->documentElement;
						}
						else
						{
							$root = $dom->appendChild($dom->createElement('i18n'));
							$root->setAttribute('baseKey', $package);
							$root->setAttribute('lcid', $locale);
						}
						foreach ($ids as $id)
						{
							$key = $root->appendChild($dom->createElement('key', ''));
							$key->setAttribute('id', $id);
						}
						$xmlContent = str_replace('  ', "\t", $dom->saveXML());
						file_put_contents($localeFile, $xmlContent);
					}
				}
			}

		}

		if ($input->getOption('find-usage'))
		{
			$key = $input->getOption('find-usage');
			if (isset($usedExplicitely[$key]))
			{
				foreach ($usedExplicitely[$key] as $file)
				{
					$output->writeln('<info>' . $file . '</info>');
				}
			}
		}

	}
}