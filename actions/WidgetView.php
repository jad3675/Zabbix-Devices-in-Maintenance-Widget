<?php declare(strict_types = 1);

namespace Modules\MaintDevices\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;

use Modules\MaintDevices\Includes\CWidgetFieldDuration;
use Modules\MaintDevices\Widget;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$hosts = $this->fetchHosts();
		$now = time();
		// Parsed with the same code the field validates with, so what was
		// accepted in the config dialog and what is acted on here cannot drift.
		// The field already rejected anything unparseable; null here would
		// mean a config written before this field existed, so fall back to off.
		$stale_after = CWidgetFieldDuration::parse((string) $this->fields_values['stale_after'])
			?? CWidgetFieldDuration::OFF;
		$collection = (int) $this->fields_values['collection'];
		$only_stale = (int) $this->fields_values['only_stale'] === 1;

		$rows = [];
		$flagged = 0;

		foreach ($hosts as $host) {
			// maintenance_type on the HOST is the mode of the window currently
			// suppressing it, not the host's own setting. That is the useful
			// reading: is this box still being polled, or has it gone dark?
			$collecting = ((int) $host['maintenance_type'] === MAINTENANCE_TYPE_NORMAL);

			if ($collection == Widget::COLLECTION_ON && !$collecting) {
				continue;
			}

			if ($collection == Widget::COLLECTION_OFF && $collecting) {
				continue;
			}

			$from = (int) $host['maintenance_from'];
			$duration = $from > 0 ? max(0, $now - $from) : 0;
			$is_stale = ($stale_after > CWidgetFieldDuration::OFF && $from > 0
				&& $duration >= $stale_after
			);

			if ($only_stale && !$is_stale) {
				continue;
			}

			if ($is_stale) {
				$flagged++;
			}

			$tags = [];

			foreach ((array) $host['tags'] as $tag) {
				$tags[] = ($tag['value'] !== '') ? $tag['tag'].': '.$tag['value'] : $tag['tag'];
			}

			sort($tags, SORT_NATURAL | SORT_FLAG_CASE);

			$rows[] = [
				'hostid' => (string) $host['hostid'],
				'name' => (string) $host['name'],
				'host' => (string) $host['host'],
				'ip' => $this->firstAddress($host),
				'tags' => $tags,
				'collecting' => $collecting,
				'enabled' => ((int) $host['status'] === HOST_STATUS_MONITORED),
				'from' => $from,
				'duration' => $duration,
				'duration_text' => $from > 0 ? $this->shortDuration($duration) : '',
				'stale' => $is_stale
			];
		}

		if ((int) $this->fields_values['sort_by'] === Widget::SORT_DURATION) {
			usort($rows, static function (array $a, array $b): int {
				return $b['duration'] <=> $a['duration'];
			});
		}
		else {
			usort($rows, static function (array $a, array $b): int {
				return strnatcasecmp($a['name'], $b['name']);
			});
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getDefaultName()),
			'rows' => $rows,
			'total' => count($rows),
			'flagged' => $flagged,
			'stale_after' => $stale_after,
			// The operator's own string, not a normalised one. format() would
			// turn "7d" into "1w": same duration, but not what they typed, and
			// a badge that silently rewords the threshold is mildly maddening.
			'stale_after_text' => strtolower(trim((string) $this->fields_values['stale_after'])),
			'display' => (int) $this->fields_values['display'],
			'show_tags' => (int) $this->fields_values['show_tags'] === 1,
			'user' => ['debug_mode' => $this->getDebugMode()]
		]));
	}

	/**
	 * Hosts Zabbix currently has suppressed.
	 *
	 * Deliberately host.get rather than maintenance.get. maintenance.get would
	 * only see windows somebody had a record of; this sees anything Zabbix has
	 * flagged, including windows scheduled by hand and hosts swept in by a
	 * host-group target that nobody listed individually.
	 *
	 * It also runs as the logged-in user, so the widget is permission-scoped
	 * for free and needs no elevated API token. That matters more on a
	 * dashboard than on a page: a wallboard left open for a month refreshes
	 * this every minute, and an elevated call on that cadence is an audit-log
	 * problem waiting to happen.
	 */
	private function fetchHosts(): array {
		$options = [
			'output' => ['hostid', 'host', 'name', 'status', 'maintenance_type', 'maintenance_from'],
			'selectInterfaces' => ['ip', 'dns', 'type', 'main'],
			'filter' => ['maintenance_status' => HOST_MAINTENANCE_STATUS_ON],
			'limit' => 2000
		];

		if ($this->fields_values['show_tags']) {
			$options['selectTags'] = ['tag', 'value'];
		}

		if ($this->fields_values['groupids']) {
			$options['groupids'] = $this->fields_values['groupids'];
		}

		if ($this->fields_values['hostids']) {
			$options['hostids'] = $this->fields_values['hostids'];
		}

		if ($this->fields_values['tags']) {
			$options['tags'] = $this->fields_values['tags'];
			$options['evaltype'] = (int) $this->fields_values['evaltype'];
		}

		$hosts = API::Host()->get($options);

		if (!$this->fields_values['show_tags']) {
			foreach ($hosts as &$host) {
				$host['tags'] = [];
			}
			unset($host);
		}

		return $hosts;
	}

	private function firstAddress(array $host): string {
		// Prefer the main interface; a host with several is usually best
		// identified by whichever one Zabbix itself polls.
		foreach ((array) ($host['interfaces'] ?? []) as $interface) {
			if ((int) ($interface['main'] ?? 0) === 1 && ($interface['ip'] ?? '') !== '') {
				return (string) $interface['ip'];
			}
		}

		foreach ((array) ($host['interfaces'] ?? []) as $interface) {
			if (($interface['ip'] ?? '') !== '') {
				return (string) $interface['ip'];
			}
		}

		foreach ((array) ($host['interfaces'] ?? []) as $interface) {
			if (($interface['dns'] ?? '') !== '') {
				return (string) $interface['dns'];
			}
		}

		return '';
	}

	/** Compact and glanceable: 3d 4h, 5h 20m, 12m. */
	private function shortDuration(int $seconds): string {
		if ($seconds >= SEC_PER_DAY) {
			$days = intdiv($seconds, SEC_PER_DAY);
			$hours = intdiv($seconds % SEC_PER_DAY, SEC_PER_HOUR);

			return $hours > 0 ? sprintf('%dd %dh', $days, $hours) : sprintf('%dd', $days);
		}

		if ($seconds >= SEC_PER_HOUR) {
			$hours = intdiv($seconds, SEC_PER_HOUR);
			$minutes = intdiv($seconds % SEC_PER_HOUR, SEC_PER_MIN);

			return $minutes > 0 ? sprintf('%dh %dm', $hours, $minutes) : sprintf('%dh', $hours);
		}

		return sprintf('%dm', intdiv($seconds, SEC_PER_MIN));
	}
}
