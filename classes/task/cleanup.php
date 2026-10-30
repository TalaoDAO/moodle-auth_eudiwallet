<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace auth_eudiwallet\task;
defined('MOODLE_INTERNAL') || die();
class cleanup extends \core\task\scheduled_task {
    public function get_name() { return get_string('cleanup', 'auth_eudiwallet'); }
    public function execute() { \auth_eudiwallet\local\transactions::cleanup(); }
}
