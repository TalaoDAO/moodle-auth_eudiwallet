<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
$tasks = [['classname' => '\\auth_eudiwallet\\task\\cleanup', 'blocking' => 0,
    'minute' => '*/10', 'hour' => '*', 'day' => '*', 'month' => '*', 'dayofweek' => '*']];
