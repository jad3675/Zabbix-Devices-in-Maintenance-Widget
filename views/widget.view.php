<?php declare(strict_types = 1);

/**
 * Devices in maintenance widget view.
 *
 * @var CView $this
 * @var array $data
 */

use Modules\MaintDevices\Widget;

$body = new CDiv();
$body->addClass('md-body');

$show_count = in_array($data['display'], [Widget::DISPLAY_COUNT, Widget::DISPLAY_BOTH], true);
$show_list = in_array($data['display'], [Widget::DISPLAY_LIST, Widget::DISPLAY_BOTH], true);

if ($show_count) {
	$count = (new CDiv())->addClass('md-count');

	$count->addItem((new CSpan((string) $data['total']))
		->addClass('md-count-number')
		->addClass($data['total'] == 0 ? 'md-quiet' : null)
	);

	$count->addItem((new CSpan($data['total'] == 1 ? _('device') : _('devices')))
		->addClass('md-count-label')
	);

	// The flagged count is the number worth putting on a wallboard. Anything
	// suppressed past the threshold has stopped being maintenance and started
	// being a gap in coverage that nobody is looking at.
	if ($data['stale_after'] > 0 && $data['flagged'] > 0) {
		// Echoes the threshold in the same units the operator typed it, so the
		// badge reads "2 over 90m" or "2 over 7d" rather than normalising
		// everything to a unit nobody chose.
		$count->addItem((new CSpan(sprintf(
			_('%1$d over %2$s'), $data['flagged'], $data['stale_after_text']
		)))->addClass('md-flagged'));
	}

	$body->addItem($count);
}

if ($show_list) {
	if (!$data['rows']) {
		$body->addItem((new CDiv(_('Nothing is in maintenance.')))->addClass('md-empty'));
	}
	else {
		$header = [_('Host'), _('IP')];

		if ($data['show_tags']) {
			$header[] = _('Tags');
		}

		$header[] = _('Collection');
		$header[] = _('For');

		$table = (new CTableInfo())
			->setHeader($header)
			->addClass('md-table');

		foreach ($data['rows'] as $row) {
			$name = (new CLink($row['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'latest.view')
					->setArgument('hostids', [$row['hostid']])
					->getUrl()
			))->addClass('md-host');

			$host_cell = [$name];

			if ($row['host'] !== $row['name']) {
				$host_cell[] = (new CDiv($row['host']))->addClass('md-sub');
			}

			if (!$row['enabled']) {
				$host_cell[] = (new CDiv(_('host disabled')))->addClass('md-sub');
			}

			$cells = [$host_cell, (new CCol($row['ip']))->addClass('md-mono')];

			if ($data['show_tags']) {
				$tags = new CDiv();
				$tags->addClass('md-tags');

				foreach ($row['tags'] as $tag) {
					$tags->addItem((new CSpan($tag))->addClass('md-tag'));
				}

				$cells[] = $tags;
			}

			$cells[] = (new CSpan($row['collecting'] ? _('collecting') : _('no data')))
				->addClass('md-pill')
				->addClass($row['collecting'] ? 'md-pill-ok' : 'md-pill-nodata');

			$cells[] = (new CCol($row['duration_text']))
				->addClass('md-mono')
				->addClass($row['stale'] ? 'md-stale' : null);

			$table->addRow((new CRow($cells))->addClass($row['stale'] ? 'md-row-stale' : null));
		}

		$body->addItem($table);
	}
}

(new CWidgetView($data))
	->addItem($body)
	->show();
