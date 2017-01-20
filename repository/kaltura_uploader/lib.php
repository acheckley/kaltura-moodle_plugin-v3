<?php


require_once($CFG->dirroot . '/repository/lib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once($CFG->dirroot . '/repository/upload/lib.php');


class repository_kaltura_uploader extends repository {

    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);
    }

    public function check_login() {
        return !empty($this->keyword);
    }

    public function global_search() {
        return false;
    }

    public function print_login($ajax = false) {

        return $this->get_listing();
    }

    public function upload($saveas_filename, $maxbytes) {
        global $CFG;

        $types    = optional_param_array('accepted_types', '*', PARAM_RAW);
        $savepath = optional_param('savepath', '/', PARAM_PATH);
        $itemid   = optional_param('itemid', 0, PARAM_INT);
        $license  = optional_param('license', $CFG->sitedefaultlicense, PARAM_TEXT);
        $author   = optional_param('author', '', PARAM_TEXT);
        $overwriteexisting = optional_param('overwrite', false, PARAM_BOOL);

        return $this->process_upload($saveas_filename, $maxbytes, $types, $savepath, $itemid, $license, $author, $overwriteexisting);
    }

    public function process_upload($saveas_filename, $maxbytes, $types = '*', $savepath = '/', $itemid = 0, $license = null, $author = '', $overwriteexisting = false) {
        global $DB;

        $elname = 'repo_upload_file';

        // Upload video to Kaltura //
        $kaltura = new kaltura_connection();
        $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

        if (!$connection) {
            throw new moodle_exception('Unable to connect to Kaltura');
        }

        $mediaEntry            = new KalturaMediaEntry();
        $mediaEntry->name      = $_FILES[$elname]['name'];
        $mediaEntry->mediaType = KalturaMediaType::VIDEO;
        $mediaEntry            = $connection->media->add($mediaEntry);

        $uploadToken = $connection->uploadToken->add();
        $connection->uploadToken->upload($uploadToken->id, $_FILES[$elname]['tmp_name']);

        $mediaResource = new KalturaUploadedFileTokenResource();
        $mediaResource->token = $uploadToken->id;
        $mediaEntry = $connection->media->addContent($mediaEntry->id, $mediaResource);

        if ( !$mediaEntry instanceof KalturaMediaEntry) {
            throw new moodle_exception('upload_kaltura_error_process_upload', 'repository_kaltura_upload');
        }

        $uri         = local_kaltura_get_host();
        $uri         = rtrim($uri, '/');
        $partner_id  = local_kaltura_get_partner_id();
        $ui_conf_id  = local_kaltura_get_player_uiconf();


        $source = $uri.'/index.php/kwidget/wid/_'.$partner_id.
                  '/uiconf_id/'.$ui_conf_id.'/entry_id/' . $mediaEntry->id . '/v/flash#'.
                  $mediaEntry->name;

        return array(
            'url'=> $source,
            'id'=> $itemid,
            'file'=> $mediaEntry->name);

    }

    public function get_listing($path='', $page = '') {
        global $CFG;
        $ret = array();
        $ret['nologin']  = true;
        $ret['nosearch'] = true;
        $ret['norefresh'] = true;
        $ret['list'] = array();
        $ret['dynload'] = false;
        //$ret['allowcaching'] = true; // indicates that result of get_listing() can be cached in filepicker.js
        $ret['upload'] = array('label'=>get_string('attachment', 'repository'), 'id'=>'repo-form');

        return $ret;

    }

    /**
     * file types supported by Kaltura plugin
     * @return array
     */
//    public function supported_filetypes() {
//        return array('web_video', 'web_audio', 'web_image');
//    }

    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }
}