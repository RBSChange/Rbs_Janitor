<?php
namespace Rbs\Janitor\Setup;

/**
 * @name \Rbs\Janitor\Setup\Install
 */
class Install extends \Change\Plugins\InstallBase
{
	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param \Change\Application $application
	 * @param \Change\Configuration\EditableConfiguration $configuration
	 * @throws \RuntimeException
	 */
	public function executeApplication($plugin, $application, $configuration)
	{
		$configuration->addPersistentEntry('Change/Events/Commands/Rbs_Janitor', '\Rbs\Janitor\Commands\Listeners');
	}
}
