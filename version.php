<?php
/**
 * SIM Web Services
 *
 * @copyright 2015 lbtsoft
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

$plugin->version  = 2016071700;   // The (date) version of this module + 2 extra digital for daily versions
                                  // This version number is displayed into /admin/forms.php
                                  // TODO: if ever this plugin get branched, the old branch number
                                  // will not be updated to the current date but just incremented. We will
                                  // need then a $plugin->release human friendly date. For the moment, we use
                                  // display this version number with userdate (dev friendly)
$plugin->requires = 2010112400;  // Requires this Moodle version - at least 2.0
$plugin->component= 'local_wssim';
$plugin->cron     = 0;
//$plugin->release = '1.0 (Build: xxxxxxx)';
$plugin->maturity = MATURITY_STABLE;