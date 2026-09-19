<?php declare(strict_types = 1);

namespace Modules\MaintDevices\Includes;

use Zabbix\Widgets\Fields\CWidgetFieldTextBox;

/**
 * A duration entered the way Zabbix writes durations everywhere else:
 * 45s, 90m, 22h, 7d, 2w, or combinations like 1h30m. 0 turns it off.
 *
 * Renders with the stock CWidgetFieldTextBoxView, so the config dialog needs
 * nothing special. The only thing this class adds is validation, so a typo is
 * caught when the widget is saved rather than silently producing a threshold
 * nobody intended.
 *
 * A bare number is REJECTED rather than treated as seconds. This field
 * replaced an integer "Flag after (days)" field, and somebody typing 7 out of
 * habit meaning a week should get an error, not a seven second threshold that
 * flags the entire estate.
 */
class CWidgetFieldDuration extends CWidgetFieldTextBox {

	public const OFF = 0;

	/** One minute. Below this the threshold is noise. */
	public const MIN = 60;

	/** Ten years. Above this somebody has misunderstood the field. */
	public const MAX = 315360000;

	private const UNITS = [
		's' => 1,
		'm' => 60,
		'h' => 3600,
		'd' => 86400,
		'w' => 604800
	];

	/**
	 * Shared by the field's validation and the widget controller, so the thing
	 * that accepts a value and the thing that acts on it can never disagree
	 * about what it means.
	 *
	 * @return int|null seconds, 0 for off, or null if unparseable
	 */
	public static function parse(?string $text): ?int {
		$text = strtolower(trim((string) $text));

		if ($text === '' || $text === '0') {
			return self::OFF;
		}

		// Digits with no unit are ambiguous and therefore refused.
		if (!preg_match('/^(\d+[smhdw])+$/', $text)) {
			return null;
		}

		preg_match_all('/(\d+)([smhdw])/', $text, $matches, PREG_SET_ORDER);

		$total = 0;

		foreach ($matches as $match) {
			$total += (int) $match[1] * self::UNITS[$match[2]];
		}

		return $total;
	}

	/**
	 * Tidy display form: 5400 -> "90m". Only for places that have seconds and
	 * no original string; the widget badge shows what the operator typed.
	 */
	public static function format(int $seconds): string {
		if ($seconds <= 0) {
			return '0';
		}

		foreach (['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60] as $suffix => $size) {
			if ($seconds % $size === 0) {
				return ($seconds / $size).$suffix;
			}
		}

		return $seconds.'s';
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		$raw = (string) $this->getValue();
		$seconds = self::parse($raw);

		if ($seconds === null) {
			return [sprintf(
				_('Invalid value "%1$s" for "%2$s": use a time unit such as 90m, 22h or 7d, or 0 to switch it off.'),
				$raw, $this->getLabel()
			)];
		}

		if ($seconds !== self::OFF && $seconds < self::MIN) {
			return [sprintf(
				_('"%s" is too short: the shortest threshold is 1m.'), $raw
			)];
		}

		if ($seconds > self::MAX) {
			return [sprintf(
				_('"%s" is longer than ten years.'), $raw
			)];
		}

		return [];
	}
}
