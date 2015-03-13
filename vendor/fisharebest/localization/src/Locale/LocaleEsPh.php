<?php namespace Fisharebest\Localization;

/**
 * Class LocaleEsPh
 *
 * @author        Greg Roach <fisharebest@gmail.com>
 * @copyright (c) 2015 Greg Roach
 * @license       GPLv3+
 */
class LocaleEsPh extends LocaleEs {
	/** {@inheritdoc} */
	public function territory() {
		return new TerritoryPh;
	}
}