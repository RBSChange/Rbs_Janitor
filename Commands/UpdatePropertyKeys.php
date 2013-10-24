<?php
namespace Rbs\Janitor\Commands;

use Change\Commands\Events\Event;
use Symfony\Component\Finder\Finder;

/**
 * @name \Rbs\Janitor\Commands\UpdatePropertyKeys
 */
class UpdatePropertyKeys
{
	/**
	 * @param Event $event
	 */
	public function execute(Event $event)
	{
		$application = $event->getApplication();
		$as = new \Change\Application\ApplicationServices($application);
		$ds = new \Change\Documents\DocumentServices($as);

		$searchKeys = [];
		$replaceKeys = [];

		foreach ($ds->getModelManager()->getModelsNames() as $modelName)
		{
			$model = $ds->getModelManager()->getModelByName($modelName);

			$searchKeys[] = "'" . strtolower(implode('.', ['m', $model->getVendorName(), $model->getShortModuleName(), 'documents', $model->getShortName()])) . "'";
			$searchKeys[] = '"' . strtolower(implode('.', ['m', $model->getVendorName(), $model->getShortModuleName(), 'documents', $model->getShortName()])) . '"';
			$replaceKeys[] = 'modelKey(\'' . $modelName . '\')';
			$replaceKeys[] = 'modelKey(\'' . $modelName . '\')';

			foreach ($model->getPropertyNames() as $pName)
			{
				$searchKeys[] = "'" . strtolower(implode('.', ['m', $model->getVendorName(), $model->getShortModuleName(), 'documents', $model->getShortName(), $pName])) . "'";
				$searchKeys[] = '"' . strtolower(implode('.', ['m', $model->getVendorName(), $model->getShortModuleName(), 'documents', $model->getShortName(), $pName])) . '"';

				$replaceKeys[] = 'propertyKey(\'' . $modelName . '\', \'' . $pName . '\')';
				$replaceKeys[] = 'propertyKey(\'' . $modelName . '\', \'' . $pName . '\')';

			}
		}

		$path = $event->getParam('path');

		if ($path === null)
		{
			$path = $application->getWorkspace()->projectPath();
		}
		$finder = new Finder();
		$finder->ignoreVCS(true)->ignoreDotFiles(true)->ignoreUnreadableDirs(true);
		$finder->in(realpath($path));
		$finder->exclude(array('tmp', 'Compilation', 'Libraries', 'ChangeTests', $application->getConfiguration('Change/Install/webBaseDirectory')));
		$finder->files()->name('*.twig');

		foreach ($finder as $file)
		{
			/** @var $file \SplFileInfo */
			$content = $file->getContents();
			$count = 0;
			$content = str_ireplace($searchKeys, $replaceKeys, $content, $count);
			if ($count)
			{
				file_put_contents($file->getPathname(), $content);
			}
		}

		$event->addInfoMessage('Done.');
	}
}