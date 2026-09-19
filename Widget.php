<?php declare(strict_types = 1);

namespace Modules\MaintDevices;

use Zabbix\Core\CWidget;

/**
 * Devices in maintenance.
 *
 * Zabbix will happily include or exclude hosts in maintenance anywhere you
 * look, but it will not isolate them: Host navigator's "Show hosts in
 * maintenance" and the Monitoring -> Hosts filter of the same name are both
 * additive toggles. There is no "only what is suppressed" view in the product,
 * and there is certainly not one on a dashboard.
 *
 * The usual workaround is a zabbix[host,,maintenance] internal item on every
 * template, which is a lot of item churn for a fact Zabbix already tracks in
 * the hosts table. This widget reads it straight from there instead.
 */
class Widget extends CWidget {

	public const DISPLAY_COUNT = 0;
	public const DISPLAY_LIST = 1;
	public const DISPLAY_BOTH = 2;

	public const COLLECTION_ANY = 0;
	public const COLLECTION_ON = 1;
	public const COLLECTION_OFF = 2;

	public const SORT_NAME = 0;
	public const SORT_DURATION = 1;

	public const SOURCE_ANY = 0;
	public const SOURCE_EXTERNAL = 1;
}
