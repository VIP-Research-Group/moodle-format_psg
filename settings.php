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
 * Settings for format_psg.
 *
 * @package    format_psg
 * @copyright  2021 Athabasca University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading(
        'headerconfig1',
        get_string('settingsheader', 'format_psg'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_psg/withinsection',
        get_string('withinlabel', 'format_psg'),
        get_string('withindesc', 'format_psg'),
        '1'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'format_psg/bysection',
        get_string('bylabel', 'format_psg'),
        get_string('bydesc', 'format_psg'),
        '0'
    ));

    $settings->add(new admin_setting_heading(
        'headerconfig2',
        get_string('settingsheader2', 'format_psg'),
        ''
    ));

    $url = ['href' => new moodle_url('/blocks/behaviour/documentation.php#howpsg')];
    $a = html_writer::tag('a', get_string('psgdocs', 'format_psg'), $url);
    $desc = get_string('useilsdesc', 'format_psg', $a);

    $settings->add(new admin_setting_configcheckbox(
        'format_psg/useils',
        get_string('useilslabel', 'format_psg'),
        $desc,
        '1'
    ));
}
