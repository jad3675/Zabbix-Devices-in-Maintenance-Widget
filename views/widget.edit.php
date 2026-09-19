<?php declare(strict_types = 1);

/**
 * Devices in maintenance widget configuration form.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
	->addField(new CWidgetFieldMultiSelectGroupView($data['fields']['groupids']))
	->addField(new CWidgetFieldMultiSelectHostView($data['fields']['hostids']))
	->addField(new CWidgetFieldRadioButtonListView($data['fields']['evaltype']))
	->addField(new CWidgetFieldTagsView($data['fields']['tags']))
	->addField(new CWidgetFieldRadioButtonListView($data['fields']['display']))
	->addField(new CWidgetFieldRadioButtonListView($data['fields']['collection']))
	// CWidgetFieldDuration extends CWidgetFieldTextBox, so the stock text box
	// view renders it. Only the validation differs.
	->addField(new CWidgetFieldTextBoxView($data['fields']['stale_after']))
	->addField(new CWidgetFieldCheckBoxView($data['fields']['only_stale']))
	->addField(new CWidgetFieldRadioButtonListView($data['fields']['sort_by']))
	->addField(new CWidgetFieldCheckBoxView($data['fields']['show_tags']))
	->show();
