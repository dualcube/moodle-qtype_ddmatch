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
 * 
 * @package    qtype_ddmatch
 * 
 * @author DualCube <admin@dualcube.com>
 * @copyright  2007 DualCube (https://dualcube.com) 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'qtype_ddmatch';
$plugin->version = 2025051900;
$plugin->requires = 2022112815;
$plugin->maturity = MATURITY_STABLE;
$plugin->dependencies = array(
    'qtype_match' => 2015111600,
);
$plugin->release = '2.5.4 (Build: 2025051900)';
