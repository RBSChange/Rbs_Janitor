<?php
namespace Rbs\Janitor\Commands;

use Change\Events\EventManagerFactory;
use Change\Services\ApplicationServices;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;
use Zend\Stdlib\Glob;

/**
 * @name \Rbs\Janitor\Commands\ExportI18n
 */
class GenerateCrowdinConfig extends Command
{
	protected function configure()
	{
		$this->setName('rbs_janitor:generate-crowdin-config');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var $app \Change\Application */
		$app = $this->getApplication()->getChangeApplication();
		$em = new EventManagerFactory($app);
		$as = new ApplicationServices($app, $em);
		$LCID = 'fr_FR';

		$data = Yaml::parse(file_get_contents($app->getWorkspace()->projectPath('crowdin.yaml')));
		$data['files'] = [];
		$basePathLength = strlen($data["base_path"]);

		foreach($as->getPluginManager()->getInstalledPlugins() as $plugin)
		{
			$localeFilePattern = implode(DIRECTORY_SEPARATOR, [$plugin->getAssetsPath(), 'I18n', $LCID, '*.json']);
			foreach(Glob::glob($localeFilePattern, Glob::GLOB_NOESCAPE + Glob::GLOB_NOSORT) as $filePath)
			{
				$relPath = substr($filePath, $basePathLength);
				$data['files'][] = ["source" =>  $relPath, 'translation' => str_replace('fr_FR', '%locale_with_underscore%', $relPath)];
			}
		}
		$localeFilePattern = $app->getWorkspace()->changePath('Assets', 'I18n', $LCID, '*.json');
		foreach(Glob::glob($localeFilePattern, Glob::GLOB_NOESCAPE + Glob::GLOB_NOSORT) as $filePath)
		{
			$relPath = substr($filePath, $basePathLength);
			$data['files'][] = ["source" =>  $relPath, 'translation' => str_replace('fr_FR', '%locale_with_underscore%', $relPath)];
		}
		$dumper = new Dumper();
		file_put_contents($app->getWorkspace()->projectPath('crowdin.yaml'), $dumper->dump($data, 2));
	}

}