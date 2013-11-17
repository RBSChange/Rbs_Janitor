<?php
namespace Rbs\Janitor\Commands;

use Change\Events\EventManagerFactory;
use Change\I18n\PreparedKey;
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
 * @name \Rbs\Janitor\Commands\MigrateLocales
 */
class MigrateLocales extends Command
{

	protected $locales = [];

	protected function configure()
	{
		$this->setName('rbs_janitor:migrate-locales')
			->addArgument('vendor', InputArgument::REQUIRED, 'plugin vendor')
			->addArgument('name', InputArgument::REQUIRED, 'plugin name')
			->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'plugin type', 'module')
			->addOption('convert', null, InputOption::VALUE_NONE, 'convert')
			->addOption('dispatch', null, InputOption::VALUE_NONE, 'dispatch')
			->addOption('finalize', null, InputOption::VALUE_NONE, 'finalize');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if ($input->getOption('convert'))
		{
			$this->convert($input->getOption('type'), $input->getArgument('vendor'), $input->getArgument('name'));
		}
		if ($input->getOption('dispatch'))
		{
			$this->dispatch($input->getOption('type'), $input->getArgument('vendor'), $input->getArgument('name'));
		}
		if ($input->getOption('finalize'))
		{
			$this->finalize($input->getOption('type'), $input->getArgument('vendor'), $input->getArgument('name'));
		}
	}

	protected function finalize($type, $vendor, $name)
	{

		$destinationPackages = [];
		$tmpFiles = [];

		// Move english locales
		$app = $this->getApplication()->getChangeApplication();
		$em = new EventManagerFactory($app);
		$as = new ApplicationServices($app, $em);
		$plugin = $as->getPluginManager()->getPlugin($type, $vendor, $name);
		$substitutions = [];
		$substitutionsFilePath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'substitutions.tmp';
		if (file_exists($substitutionsFilePath))
		{
			$substitutions = json_decode(file_get_contents($substitutionsFilePath), true);
		}

		if (count($substitutions) == 0)
		{
			return;
		}

		// Load existing locales files
		$baseI18nPath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'en_US';
		if (!is_dir($baseI18nPath))
		{
			@mkdir($baseI18nPath, 0755, true);
		}


		$finder = new Finder();
		$finder->ignoreVCS(true)->ignoreDotFiles(true)->ignoreUnreadableDirs(true);
		$finder->in($baseI18nPath);
		$finder->files()->name('*.json');

		foreach ($finder as $file)
		{
			$packageName = strtolower(implode('.', [($type === 'module') ?  'm' : 't', $vendor, $name, substr($file->getFilename(), 0 , -5)]));
			$destinationPackages[$packageName] = json_decode($file->getContents(), true);
		}

		foreach ($substitutions as $oldKey => $newKey)
		{
			$oldFilePathParts = explode('.', $oldKey);
			// $lazy
			array_shift($oldFilePathParts);
			array_shift($oldFilePathParts);
			array_shift($oldFilePathParts);

			$key = array_pop($oldFilePathParts);
			$basePath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR;
			$xmlPath = $basePath . implode(DIRECTORY_SEPARATOR,  $oldFilePathParts) . DIRECTORY_SEPARATOR . 'en_US.xml';
			if (file_exists($xmlPath))
			{
				$dom = new \DOMDocument("1.0", "UTF-8");
				$dom->load($xmlPath);
				$xpath = new \DOMXPath($dom);
				$nodes = $xpath->query("//key[@id='".  $key .  "']");
				$value = null;
				foreach($nodes as $node)
				{
					$value = $node->textContent;
				}
				if ($value)
				{
					$parts = explode('.', $newKey);
					$id = array_pop($parts);
					$packageName = implode('.', $parts);
					$destinationPackages[$packageName][$id] = ["message" => $value];
				}
			}
		}

		$baseI18nPath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'en_US';
		foreach ($destinationPackages as $packageName => $content)
		{
			$parts = explode('.', $packageName);
			$fileName = array_pop($parts) . '.json';
			$path = $baseI18nPath . DIRECTORY_SEPARATOR . $fileName;
			\Change\Stdlib\File::write($path, Json::prettyPrint(json_encode($content, JSON_UNESCAPED_UNICODE)));
		}

		$finder = new Finder();
		$finder->ignoreVCS(true)->ignoreDotFiles(true)->ignoreUnreadableDirs(true);
		$finder->in($app->getWorkspace()->projectPath());
		$finder->depth('> 0');
		$finder->exclude(array('tmp', 'App', 'Compilation', 'Libraries', 'ChangeTests', $app->getConfiguration('Change/Install/webBaseDirectory')));
		$finder->files()->name('*.php')->name('*.js')->name('*.json')->name('*.twig');

		$searchKeys = [];
		$replaceKeys = [];
		foreach ($substitutions as $key => $val)
		{
			$searchKeys[] = "'" . $key . "'";
			$searchKeys[] = '"' . $key . '"';
			$replaceKeys[] = "'" . $val . "'";
			$replaceKeys[] = '"' . $val . '"';
		}

		foreach ($finder as $file)
		{
			$content = $file->getContents();
			$content = str_replace($searchKeys, $replaceKeys, $content, $count);
			if ($count)
			{
				file_put_contents($file->getPathname(), $content);
			}
		}

		$json = file_get_contents($plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'plugin.json');
		$data = json_decode($json, true);
		$data['defaultLCID'] = 'fr_FR';
		\Change\Stdlib\File::write($plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'plugin.json', Json::prettyPrint(json_encode($data, JSON_UNESCAPED_UNICODE)));

	}

	protected function dispatch($type, $vendor, $name)
	{
		$destinationPackages = [];
		$tmpFiles = [];
		$remainder = [];

		$app = $this->getApplication()->getChangeApplication();
		$em = new EventManagerFactory($app);
		$as = new ApplicationServices($app, $em);
		$plugin = $as->getPluginManager()->getPlugin($type, $vendor, $name);
		if ($plugin)
		{
			// Load temp locale files
			$baseI18nTmpPath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'Tmp';
			$finder = new Finder();
			$finder->ignoreVCS(true)->ignoreDotFiles(true)->ignoreUnreadableDirs(true);
			$finder->in($baseI18nTmpPath);
			$finder->files()->name('*.json');

			foreach ($finder as $file)
			{
				/** @var $file \SplFileInfo */
				$tmpFiles[$file->getFilename()] = json_decode($file->getContents(), true);
			}

			// Load existing locales files
			$baseI18nPath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'fr_FR';
			if (!is_dir($baseI18nPath))
			{
				@mkdir($baseI18nPath, 0755, true);
			}


			$finder = new Finder();
			$finder->ignoreVCS(true)->ignoreDotFiles(true)->ignoreUnreadableDirs(true);
			$finder->in($baseI18nPath);
			$finder->files()->name('*.json');

			foreach ($finder as $file)
			{
				$packageName = strtolower(implode('.', [($type === 'module') ?  'm' : 't', $vendor, $name, substr($file->getFilename(), 0 , -5)]));
				/** @var $file \SplFileInfo */
				$destinationPackages[$packageName] = json_decode($file->getContents(), true);
			}

			$substitutions = [];
			$substitutionsFilePath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'substitutions.tmp';
			if (file_exists($substitutionsFilePath))
			{
				$substitutions = json_decode(file_get_contents($substitutionsFilePath), true);
			}

			$htmlKeys = [];
			$htmlKeysFilePath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'html.tmp';
			if (file_exists($htmlKeysFilePath))
			{
				$htmlKeys = json_decode(file_get_contents($htmlKeysFilePath), true);
			}

			$remainingLocales = [];
			// We iterate on every tmpFile and try to move things
			foreach ($tmpFiles as $fileName => $content)
			{
				$remainingLocales[$fileName] = [];
				$globalMoveTo = null;
				if (isset($content['__moveTo']))
				{
					$globalMoveTo = $content['__moveTo'];
				}

				foreach ($content as $key => $data)
				{
					$moveTo = null;
					$suffix = null;
					if (strpos($key, '__') === false)
					{
						if (isset($data['__moveTo']))
						{
							$moveTo = $data['__moveTo'];
						}
					}
					if ($moveTo === null && $globalMoveTo)
					{
						$moveTo = $globalMoveTo;
					}

					if ($moveTo)
					{
						$moveToParts = explode('.', $moveTo);
						if (count($moveToParts) == 5)
						{
							$suffix = array_pop($moveToParts);
							$moveTo = implode('.', $moveToParts);
						}
						if (!preg_match(PreparedKey::KEY_REGEXP, $moveTo . '.test_key'))
						{
							$data['__error'] = 'Invalid destination package';
							$remainder[$fileName][$key] = $data;
						}
						else
						{
							if ($suffix)
							{
								$key = $suffix . '_' . $key;
							}
							if (isset($destinationPackages[$moveTo][$key]) && !isset($data['__force']))
							{
								$data['__error'] = 'Key already exists in destination package';
								$remainder[$fileName][$key] = $data;
							}
							else if (isset($data['__originalKey']))
							{
								$substitutions[$data['__originalKey']] = $moveTo . '.' . $key;
								if (isset($data['__moveTo'])) unset($data['__moveTo']);
								if (isset($data['__error'])) unset($data['__error']);
								if (isset($data['__force'])) unset($data['__force']);
								if (isset($data['__format'])) {
									$htmlKeys[] = $moveTo . '.' . $key;
									unset($data['__format']);
								}
								unset($data['__originalKey']);
								$destinationPackages[$moveTo][$key] = $data;
							}
						}
					}
					else
					{
						$data['__error'] = 'You have to tell where this locale should be moved';
						$remainder[$fileName][$key] = $data;
					}
				}
				if (!isset($remainder[$fileName]))
				{
					$remainder[$fileName] = [];
				}
				if (count($remainder[$fileName]) && $globalMoveTo)
				{
					$remainder[$fileName]['__moveTo'] = $globalMoveTo;
				}

			}
		}

		foreach ($destinationPackages as $packageName => $content)
		{
			$parts = explode('.', $packageName);
			$fileName = array_pop($parts) . '.json';
			$path = $baseI18nPath . DIRECTORY_SEPARATOR . $fileName;
			\Change\Stdlib\File::write($path, Json::prettyPrint(json_encode($content, JSON_UNESCAPED_UNICODE)));
		}

		foreach ($remainder as $name => $content)
		{
			$path = $baseI18nTmpPath . DIRECTORY_SEPARATOR . $name;
			if (count($content))
			{
				\Change\Stdlib\File::write($path, Json::prettyPrint(json_encode($content, JSON_UNESCAPED_UNICODE)));
			}
			else
			{
				unlink($path);
			}
		}

		file_put_contents($substitutionsFilePath, Json::prettyPrint(json_encode($substitutions, JSON_UNESCAPED_UNICODE)));
		$htmlKeys = array_unique($htmlKeys);
		if (count($htmlKeys))
		{
			file_put_contents($htmlKeysFilePath, Json::prettyPrint(json_encode($htmlKeys, JSON_UNESCAPED_UNICODE)));
		}
	}



	protected function convert($type, $vendor, $name)
	{
		$app = $this->getApplication()->getChangeApplication();
		$em = new EventManagerFactory($app);
		$as = new ApplicationServices($app, $em);
		$plugin = $as->getPluginManager()->getPlugin($type, $vendor, $name);
		if ($plugin)
		{
			$baseOldI18nPath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'Assets';
			$finder = new Finder();
			$finder->ignoreVCS(true)->ignoreDotFiles(true)->ignoreUnreadableDirs(true);
			$finder->in($baseOldI18nPath);
			$finder->files()->name('fr_FR.xml');
			foreach ($finder as $file)
			{
				$pathParts = [];
				/** @var $file \SplFileInfo */
				$relativePath = substr($file->getPathname(), strlen($baseOldI18nPath) + 1);
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

				$pathElements = explode(DIRECTORY_SEPARATOR, $relativePath);
				$fileName =  array_pop($pathElements);
				$LCID = str_replace('.xml', '', $fileName);
				$pathParts = array_merge($pathParts, $pathElements);

				$dom = new \DOMDocument('1.0', 'UTF-8');
				$dom->load($file->getPathname());
				foreach ($dom->getElementsByTagName('key') as $keyElement)
				{
					$value = $this->fixSubstitutions($keyElement->textContent);
					$fileName = $this->generateFilename($pathElements);
					$key = str_replace('-', '_', $keyElement->getAttribute('id'));
					$this->locales[$LCID][$fileName][$key] = [
						'message' => $value,
						'__originalKey' => implode('.', $pathParts) . '.' . $keyElement->getAttribute('id'),
					];
					$format =  $keyElement->getAttribute('format');
					if (strtolower($format) == 'html' && strpos($value, '<') !== false)
					{
						$this->locales[$LCID][$fileName][$key]['__format'] = 'html';
					}

				}
			}
			foreach ($this->locales as $locale => $files)
			{
				$baseI18nPath = $plugin->getAbsolutePath($app->getWorkspace()) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR . 'Tmp';
				foreach ($files as $file => $keys)
				{
					\Change\Stdlib\File::write($baseI18nPath . DIRECTORY_SEPARATOR . $file, Json::prettyPrint(json_encode($keys, JSON_UNESCAPED_UNICODE)));
				}
			}

		}
	}

	protected function fixSubstitutions($textContent)
	{
		return preg_replace_callback('/\{.*?\}/', function($matches){return mb_strtoupper(str_replace(['{', '}'], '$', $matches[0]));}, $textContent);
	}

	protected function generateFilename($pathElements)
	{
		if (count($pathElements) === 0)
		{
			$pathElements = ['i18n'];
		}
		return implode('.', $pathElements) . '.json';
	}


}