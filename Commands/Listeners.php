<?php
namespace Rbs\Janitor\Commands;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Json\Json;

/**
 * @name \Rbs\Janitor\Commands\Listeners
 */
class Listeners implements ListenerAggregateInterface
{

	/**
	 * Attach one or more listeners
	 * Implementors may add an optional $priority argument; the EventManager
	 * implementation will pass this to the aggregate.
	 * @param EventManagerInterface $events
	 * @return void
	 */
	public function attach(EventManagerInterface $events)
	{
		$callback = function (\Change\Commands\Events\Event $event)
		{
			$commandConfigPath = __DIR__ . '/Assets/config.json';
			if (is_file($commandConfigPath))
			{
				return Json::decode(file_get_contents($commandConfigPath), Json::TYPE_ARRAY);
			}
		};
		$events->attach('config', $callback);

		$callback = function (\Change\Commands\Events\Event $event)
		{
			/** @var $app \Change\Application\Console\ConsoleApplication*/
			$app = $event->getTarget();
			$app->add(new ScanLocales());
			$app->add(new MigrateLocales());
			$app->add(new ConvertXMLI18nFile());

		};
		$events->attach('command', $callback);

		$callback = function ($event)
		{
			$cmd = new \Rbs\Janitor\Commands\UpdatePropertyKeys();
			$cmd->execute($event);
		};
		$events->attach('rbs_janitor:update-property-keys', $callback);
	}

	/**
	 * Detach all previously attached listeners
	 * @param EventManagerInterface $events
	 * @return void
	 */
	public function detach(EventManagerInterface $events)
	{
		// TODO: Implement detach() method.
	}
}