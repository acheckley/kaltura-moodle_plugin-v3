<?php
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
 * Kaltura video assignment grade preferences form
 *
 * @package    Repository
 * @subpackage Kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Required strings
$string['pluginname'] = 'Kaltura';
$string['configplugin'] = 'Kaltura Configuration';
$string['pluginname_help'] = 'A Kaltura Repository';

// Settings page strings
$string['itemsperpage'] = 'Files to display on a page';
$string['itemsperpage_help'] = '<p>This is the number of video entries that will be displayed on the page at one time.  Additional video entries will be paged.</p>';
$string['five'] = '5';
$string['ten'] = '10';
$string['fifteen'] = '15';
$string['twenty'] = '20';
$string['twentyfive'] = '25';
$string['thirty'] = '30';
$string['fifty'] = '50';
$string['onehundred'] = '100';
$string['twohundred'] = '200';
$string['threehundred'] = '300';
$string['fourhundred'] = '400';
$string['fivehundred'] = '500';
$string['connection_status'] = 'Connection Status';
$string['connected'] = 'Connection to Kaltura successful';
$string['not_connected'] = 'Connection to Kaltura failed';
$string['using_metadata_profile'] = 'Metadata Profile';
$string['metadata_profile_found'] = 'User Metadata %a';
$string['metadata_profile_not_found'] = 'No Metadata profile found';
$string['metadata_profile_error'] = 'Error creating metadata profile';
$string['metadata_profile_info'] = '{$a->profilename} (Profile Id: {$a->profileid} | Created: {$a->created})';
$string['rootcategory'] = 'Root category path';
$string['rootcategory_help'] = '<p>Set the root category path to create a category/sub-category structure, in the KMC, to organize all of the Moodle course categories.  '.
                               'For example: <b>Sites>My Moodle Site</b>, will create a KMC category called "Sites" and a sub category called "My Moodle Site".  '.
                               'All of your Moodle course categories will created as a sub directories of "My Moodle Site".</p>';
$string['rootcategory_warning'] = 'The root category has already been set.  If you change the name all Moodle course category related data on the KMC will be lost';
$string['rootcategory_created'] = 'Root category created with the following structure <b>{$a}</b>';
$string['rootcategory_create'] = 'Please specify a root category.';
$string['unable_to_create'] = 'Unable to create root category as <b>{$a}</b>.  Please Choose another name(s) for the root category';
$string['resetroot'] = 'Reset category location';
$string['confirm_category_reset'] = '<p>Are you sure you want to reset the root category location?</p><p>If you perform this action, all video course sharing and usage information in Moodle will be lost.</p>'.
                                    '<p>If you accidentially click "continue", it is possible to get your information back, but only if you set the category path back to the <b>original</b> value.</p>'.
                                    '<p>Choose wisely.</p>';
$string['category_reset_complete'] = '<b>Root category has been reset</b>';
$string['no_permission_metadata'] = 'In order to use the Kaltura repository plug-in your account must have custom metadata enabled.  Please consult with your Kaltura rep.';
$string['no_permission_metadata_error'] = 'Error';

// File Picker Strings
$string['keyword'] = 'Search';
$string['filter'] = 'Filter';


// Capability strings
$string['kaltura:view'] = 'View Kaltura repository';
$string['kaltura:systemvisibility'] = 'Course Video Visibility';
$string['kaltura:sharedvideovisibility'] = 'Shared Video Visibility';

// Search UI
$string['search_name_tooltip'] = 'Type in media name or tag and press enter';
$string['search_name'] = 'Media name';
$string['search_tags'] = 'Media tags';
$string['search_site'] = 'Vidoes shared with site';
$string['search_site'] = 'Videos shared with course(s)';
$string['course_filter'] = 'Courses whose name';
$string['contains'] = 'Contains';
$string['equals'] = 'Equals';
$string['startswith'] = 'Starts With';
$string['endswith'] = 'Ends With';
$string['search_own_upload'] = 'Media you own';
$string['search_shared_or_used'] = 'Search for';
$string['search_shared'] = 'Media shared with courses';
$string['search_used'] = 'Media used in courses';
$string['search_site_shared'] = 'Media shared with site';
$string['course_filter_select_title'] = 'Course name filter type';

// Browse UI
$string['folder_shared_videos'] = 'Media shared with courses';
$string['folder_shared_videos_shortname'] = 'Shared Media';
$string['folder_used_videos'] = 'Media used in courses';
$string['folder_used_videos_shortname'] = 'Used Media';
$string['folder_site_shared_videos'] = 'Media shared with site';
$string['folder_site_shared_videos_shortname'] = 'Site Shared Media';

$string['crumb_home'] = 'Home';
$string['crumb_shared'] = 'Shared with courses';
$string['crumb_used'] = 'Used in courses';
$string['crumb_site_shared'] = 'Shared with site';
