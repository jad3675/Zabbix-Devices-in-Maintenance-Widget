<?php declare(strict_types = 1);

namespace Modules\MaintDevices\Includes;

use Modules\MaintDevices\Widget;

use Zabbix\Widgets\CWidgetForm;

use Modules\MaintDevices\Includes\CWidgetFieldDuration;

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldTags
};

class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField(
				new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField(
				new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('evaltype', _('Host tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField(
				new CWidgetFieldTags('tags')
			)
			->addField(
				(new CWidgetFieldRadioButtonList('display', _('Show'), [
					Widget::DISPLAY_COUNT => _('Count'),
					Widget::DISPLAY_LIST => _('List'),
					Widget::DISPLAY_BOTH => _('Count and list')
				]))->setDefault(Widget::DISPLAY_BOTH)
			)
			// Whether the window suppressing each host is still polling it.
			// "No data" for weeks is a hole in the graphs, not a maintenance
			// window, so being able to pin a tile to just those is useful.
			->addField(
				(new CWidgetFieldRadioButtonList('collection', _('Collection'), [
					Widget::COLLECTION_ANY => _('Any'),
					Widget::COLLECTION_ON => _('Collecting'),
					Widget::COLLECTION_OFF => _('No data')
				]))->setDefault(Widget::COLLECTION_ANY)
			)
			// The point of the whole widget. A host suppressed for 40 days is
			// not in maintenance, it is forgotten, and it is the mechanism
			// behind "why didn't you alert on that outage".
			->addField(
				(new CWidgetFieldDuration('stale_after', _('Flag after')))
					->setDefault('7d')
			)
			->addField(
				new CWidgetFieldCheckBox('only_stale', _('Show only flagged'))
			)
			->addField(
				(new CWidgetFieldRadioButtonList('sort_by', _('Sort by'), [
					Widget::SORT_NAME => _('Host name'),
					Widget::SORT_DURATION => _('Longest first')
				]))->setDefault(Widget::SORT_DURATION)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_tags', _('Show host tags')))->setDefault(1)
			);
	}
}
