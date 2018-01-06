<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2017 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Bootstrap4;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Functions\FunctionsDate;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Mail;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Fisharebest\Webtrees\View;

/**
 * Class ReviewChangesModule
 */
class ReviewChangesModule extends AbstractModule implements ModuleBlockInterface {
	/** {@inheritdoc} */
	public function getTitle() {
		return /* I18N: Name of a module */ I18N::translate('Pending changes');
	}

	/** {@inheritdoc} */
	public function getDescription() {
		return /* I18N: Description of the “Pending changes” module */ I18N::translate('A list of changes that need to be reviewed by a moderator, and email notifications.');
	}

	/**
	 * Generate the HTML content of this block.
	 *
	 * @param int      $block_id
	 * @param bool     $template
	 * @param string[] $cfg
	 *
	 * @return string
	 */
	public function getBlock($block_id, $template = true, $cfg = []): string {
		global $ctype, $WT_TREE;

		$sendmail = $this->getBlockSetting($block_id, 'sendmail', '1');
		$days     = $this->getBlockSetting($block_id, 'days', '1');

		foreach (['days', 'sendmail'] as $name) {
			if (array_key_exists($name, $cfg)) {
				$$name = $cfg[$name];
			}
		}

		$changes = Database::prepare(
			"SELECT 1" .
			" FROM `##change`" .
			" WHERE status='pending'" .
			" LIMIT 1"
		)->fetchOne();

		if ($changes === '1' && $sendmail === '1') {
			// There are pending changes - tell moderators/managers/administrators about them.
			if (WT_TIMESTAMP - (int) Site::getPreference('LAST_CHANGE_EMAIL') > (60 * 60 * 24 * $days)) {
				// Which users have pending changes?
				foreach (User::all() as $user) {
					if ($user->getPreference('contactmethod') !== 'none') {
						foreach (Tree::getAll() as $tree) {
							if ($tree->hasPendingEdit() && Auth::isManager($tree, $user)) {
								I18N::init($user->getPreference('language'));

								$sender = new User(
									(object) [
										'user_id'   => null,
										'user_name' => '',
										'real_name' => $WT_TREE->getTitle(),
										'email'     => $WT_TREE->getPreference('WEBTREES_EMAIL'),
									]
								);

								Mail::send(
									$sender,
									$user,
									$sender,
									I18N::translate('Pending changes'),
									View::make('emails/pending-changes-text', ['tree' => $tree, 'user' => $user]),
									View::make('emails/pending-changes-html', ['tree' => $tree, 'user' => $user])
								);
								I18N::init(WT_LOCALE);
							}
						}
					}
				}
				Site::setPreference('LAST_CHANGE_EMAIL', WT_TIMESTAMP);
			}
		}
		if (Auth::isEditor($WT_TREE) && $WT_TREE->hasPendingEdit()) {
			$content = '';
			if (Auth::isModerator($WT_TREE)) {
				$content .= '<a href="edit_changes.php">' . I18N::translate('There are pending changes for you to moderate.') . '</a><br>';
			}
			if ($sendmail === '1') {
				$content .= I18N::translate('Last email reminder was sent ') . FunctionsDate::formatTimestamp(Site::getPreference('LAST_CHANGE_EMAIL')) . '<br>';
				$content .= I18N::translate('Next email reminder will be sent after ') . FunctionsDate::formatTimestamp((int) Site::getPreference('LAST_CHANGE_EMAIL') + (60 * 60 * 24 * $days)) . '<br><br>';
			}
			$content .= '<ul>';
			$changes = Database::prepare(
				"SELECT xref" .
				" FROM  `##change`" .
				" WHERE status='pending'" .
				" AND   gedcom_id=?" .
				" GROUP BY xref"
			)->execute([$WT_TREE->getTreeId()])->fetchAll();
			foreach ($changes as $change) {
				$record = GedcomRecord::getInstance($change->xref, $WT_TREE);
				if ($record->canShow()) {
					$content .= '<li><a href="' . e($record->url()) . '">' . $record->getFullName() . '</a></li>';
				}
			}
			$content .= '</ul>';

			if ($template) {
				if ($ctype === 'gedcom' && Auth::isManager($WT_TREE) || $ctype === 'user' && Auth::check()) {
					$config_url = Html::url('block_edit.php', ['block_id' => $block_id, 'ged' => $WT_TREE->getName()]);
				} else {
					$config_url = '';
				}

				return View::make('blocks/template', [
					'block'      => str_replace('_', '-', $this->getName()),
					'id'         => $block_id,
					'config_url' => $config_url,
					'title'      => $this->getTitle(),
					'content'    => $content,
				]);
			} else {
				return $content;
			}
		}

		return '';
	}

	/** {@inheritdoc} */
	public function loadAjax(): bool {
		return false;
	}

	/** {@inheritdoc} */
	public function isUserBlock(): bool {
		return true;
	}

	/** {@inheritdoc} */
	public function isGedcomBlock(): bool {
		return true;
	}

	/**
	 * An HTML form to edit block settings
	 *
	 * @param int $block_id
	 *
	 * @return void
	 */
	public function configureBlock($block_id) {
		if (Filter::postBool('save') && Filter::checkCsrf()) {
			$this->setBlockSetting($block_id, 'days', Filter::postInteger('num', 1, 180, 1));
			$this->setBlockSetting($block_id, 'sendmail', Filter::postBool('sendmail'));
		}

		$sendmail = $this->getBlockSetting($block_id, 'sendmail', '1');
		$days     = $this->getBlockSetting($block_id, 'days', '1');

	?>
	<p>
		<?= I18N::translate('This block will show editors a list of records with pending changes that need to be reviewed by a moderator. It also generates daily emails to moderators whenever pending changes exist.') ?>
	</p>

	<fieldset class="form-group">
		<div class="row">
			<legend class="col-form-label col-sm-3">
				<?= /* I18N: Label for a configuration option */ I18N::translate('Send out reminder emails') ?>
			</legend>
			<div class="col-sm-9">
				<?= Bootstrap4::radioButtons('sendmail', FunctionsEdit::optionsNoYes(), $sendmail, true) ?>
			</div>
		</div>
	</fieldset>

	<div class="form-group row">
		<label class="col-sm-3 col-form-label" for="days">
			<?= I18N::translate('Reminder email frequency (days)') ?>
		</label>
		<div class="col-sm-9">
			<input class="form-control" type="text" name="days" id="days" value="<?= $days ?>">
		</div>
	</div>
	<?php
	}
}
