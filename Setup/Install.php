<?php
namespace Rbs\Janitor\Setup;

/**
 * @name \Rbs\Janitor\Setup\Install
 */
class Install extends \Change\Plugins\InstallBase
{

	/**
	 * @param \Zend\EventManager\EventManagerInterface $events
	 * @param \Change\Plugins\Plugin $plugin
	 */
//	public function attach($events, $plugin)
//	{
//		parent::attach($events, $plugin);
//	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 */
//	public function initialize($plugin)
//	{
//	}

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

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 * @param \Change\Application\ApplicationServices $applicationServices
	 * @param \Change\Documents\DocumentServices $documentServices
	 * @param \Change\Presentation\PresentationServices $presentationServices
	 * @throws \RuntimeException
	 */
//	public function executeServices($plugin, $applicationServices, $documentServices, $presentationServices)
//	{
//	}

	/**
	 * @param \Change\Plugins\Plugin $plugin
	 */
//	public function finalize($plugin)
//	{
//	}
}
