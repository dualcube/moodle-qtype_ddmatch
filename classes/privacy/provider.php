<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for qtype_ddmatch.
 *
 * @package    qtype_ddmatch
 * @copyright  2019 Amr Hourani <amr.hourani@let.ethz.ch>, 2021 Thomas Ludwig, ISB
 * @author DualCube <admin@dualcube.com>
 * @copyright  2026 DualCube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_ddmatch\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for qtype_ddmatch implementing user_preference_provider.
 *
 * The drag and drop matching question type itself stores no personal data:
 * qtype_ddmatch_options and qtype_ddmatch_subquestions hold question-authoring
 * content, not user data. However, like every question type, it inherits the
 * "remember my last used default mark/penalty" behaviour from question_type,
 * which stores those defaults as per-user preferences. That is the only
 * personal data associated with this component.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('qtype_ddmatch_defaultmark', 'privacy:preference:defaultmark');
        $collection->add_user_preference('qtype_ddmatch_penalty', 'privacy:preference:penalty');
        return $collection;
    }

    /**
     * Export all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $preference = get_user_preferences('qtype_ddmatch_defaultmark', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:defaultmark', 'qtype_ddmatch');
            writer::export_user_preference('qtype_ddmatch', 'defaultmark', $preference, $desc);
        }

        $preference = get_user_preferences('qtype_ddmatch_penalty', null, $userid);
        if (null !== $preference) {
            $desc = get_string('privacy:preference:penalty', 'qtype_ddmatch');
            writer::export_user_preference('qtype_ddmatch', 'penalty', transform::percentage($preference), $desc);
        }
    }
}
