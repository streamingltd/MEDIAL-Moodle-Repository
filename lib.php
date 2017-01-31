<?php

// Helix Media Library Repository Plugin for Moodle 2.x
//
// You should have received a copy of the GNU General Public License
// along with this add-on.  If not, see <http://www.gnu.org/licenses/>.

/**
 * repository_helix_media_lib class
 * Primary repsitory class for the Helix Media Library
 *
 * @since 2.0
 * @package    repository
 * @subpackage helix_media_lib
 * @copyright  2009 Dongsheng Cai, modified by Tim Williams for Streaming LTD 2011
 * @author     Dongsheng Cai <dongsheng@moodle.com>, Tim Williams <tmw@autotrain.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#

global $CFG;
require_once($CFG->dirroot."/repository/helix_media_lib/helixlib.php");


class repository_helix_media_lib extends repository
{
 /**
 * Contructor function.
 * Creates the Moodle repo object, sets up the hml_soap object and reads any submitted form parameters from the file picker
 * @param integer $repositoryid The Moodle repository ID
 * @param object $context the authorisation context
 * @param array $options The repository config options
 **/

 public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array())
 {
  $this->keyword=optional_param('helix_media_lib_keyword', '', PARAM_RAW);
  $this->cat=optional_param('helix_media_lib_cat', '', PARAM_INT);
  $this->contrib= optional_param('helix_media_lib_contrib', '', PARAM_RAW);
  $this->filename=optional_param('helix_media_lib_filename', '', PARAM_RAW);
  parent::__construct($repositoryid, $context, $options);

  global $CFG;
  //For Moodle 2.2 to 2.2.3+
  if ($CFG->version > 2011120500 && $CFG->version < 2011120503.0)
  {
   global $USER, $DB;
   $cid=array_keys($USER->currentcourseaccess);
   if(array_key_exists(0, $cid))
    $cid=$cid[0];
   else
    $cid=1;
   $this->course=$DB->get_record("course", array("id"=>$cid));
  }
  else
  {
   global $COURSE;
   $this->course=$COURSE;
  }

  $this->hml_soap=new soap_helix_media_lib($this->get_option('api_url'), $this->id, $this->course);
 }

 /**
 * Checks to see if the user is logged in
 **/

 public function check_login()
 {
  return !empty($this->keyword);
 }

 /**
 * Performs a search operation on this repository using the supplied search term.
 * If the supplied term is blank, then the keywork read in the constructor is used.
 * All other keywords used are read in the constructor
 * @param string $search_text The search string
 * @ret array The search results in JSON format
 **/

 public function search($search_text, $page=0)
 {
  $ret  = array();
  $ret['nologin'] = true;
  if (strlen($search_text)>0)
   $this->keyword=$search_text;
  $ret['list'] = $this->hml_soap->search_media($this->keyword, $this->cat, $this->contrib, $this->filename);
  
  $ret['norefresh'] = true;
  $ret['nosearch'] = true;
  $ret['pages'] = -1;
  
  return $ret;
 }

 /**
 * Does this plugin work with the Moodle global search?
 * @return boolean Always false for HML repo
 **/

 public function global_search()
 {
  return false;
 }

 /**
 * List the contents of this repository. In pratice this performs a search using the terms read in the constructor
 * @param string $path Ignored, present for compatibility with Moodle 2.x API
 * @param string $page Ignored, present for compatibility with Moodle 2.x API
 * @ret array The search results in JSON format
 **/

 public function get_listing($path='', $page = '')
 {
  $ret  = array();
  //$ret['nologin'] = true;
  //$ret['list'] = $this->hml_soap->search_media($this->keyword, $this->cat, $this->contrib, $this->filename);
  return $ret;

 }

 /**
 * Prints the login for for the repository. In practice this shows the Helix Media Library search form
 * @param boolean $ajax true if the AJAX file picker is being used
 * @return array The login form
 **/

 public function print_login($ajax = true)
 {
  $ret = array();
  $clist=$this->hml_soap->get_all_categories();
  if (count($clist)==0)
  {
   $h=new stdClass();
   $ret['login'] = array();
   return $ret;
  }

  $keyword = new stdClass();
  $keyword->type = 'text';
  $keyword->id   = 'helix_media_lib_keyword_id';
  $keyword->name = 'helix_media_lib_keyword';
  $keyword->label = get_string('search', 'repository_helix_media_lib').': ';

  $cat = new stdClass();
  $cat->type = 'select';
  $cat->id   = 'helix_media_lib_cat_id';
  $cat->name = 'helix_media_lib_cat';
  $cat->label = get_string('cat', 'repository_helix_media_lib');
  $cat->options = $clist;

  $contrib = new stdClass();
  $contrib->type = 'text';
  $contrib->id   = 'helix_media_lib_contrib_id';
  $contrib->name = 'helix_media_lib_contrib';
  $contrib->label = get_string('contrib', 'repository_helix_media_lib').': ';

  $filename = new stdClass();
  $filename->type = 'text';
  $filename->id   = 'helix_media_lib_filename_id';
  $filename->name = 'helix_media_lib_filename';
  $filename->label = get_string('filename', 'repository_helix_media_lib').': ';

  $ret['login'] = array($keyword, $cat, $contrib, $filename);
  $ret['login_btn_label'] = get_string('search');
  $ret['login_btn_action'] = 'search';

  return $ret;
 }

 /**
 * Gets an HTML formatted version of the search form
 * @return string A String containing the form
 **/

 public function print_search()
 {
  $str='<table>'.
   '<tr>'.
   '<td><label>'.get_string('search', 'repository_helix_media_lib').': </label></td>'.
   '<td><input type="text" name="helix_media_lib_keyword" id="helix_media_lib_keyword_id" /></td>'.
   '</tr><tr>'.
   '<td><label>'.get_string('cat', 'repository_helix_media_lib').'</label></td>'.
   '<td><select name="helix_media_lib_cat" id="helix_media_lib_cat_id">';

  $cats=$this->hml_soap->get_all_categories();
  foreach ($cats as $cat)
   $str.='<option value="'.$cat->name.'">'.$cat->label.'</option>';

  $str.='</select></td>'.
   '</tr><tr>'.
   '<td><label>'.get_string('contrib', 'repository_helix_media_lib').': </label></td>'.
   '<td><input type="text" name="helix_media_lib_contrib" id="helix_media_lib_contrib_id" /></td>'.
   '</tr><tr>'.
   '<td><label>'.get_string('filename', 'repository_helix_media_lib').': </label></td>'.
   '<td><input type="text" name="helix_media_lib_filename" id="helix_media_lib_filename_id" /></td>'.
   '</tr></table>';

  return $str;
 }

 /**
 * Gets an array containg the supported file types
 * @return array Single element array containing 'web_video'
 **/

 public function supported_filetypes()
 {
  return array('web_video');
 }

 /**
 * Gets the repository type
 * @return integer always FILE_EXTERNAL
 **/

 public function supported_returntypes()
 {
  return FILE_EXTERNAL;
 }

 /**
 * Gets the names of the repository config options as an array
 * @return array The array of config option names
 **/

 public static function get_instance_option_names()
 {
  return array('api_url', 'http_host', 'vstream_url', 'astream_url', 'v_height', 'v_width', 'video_quality');
 }

 /**
 * Creates the repository instance config form
 * @param object $mform The Moodle form to add the config elements
 **/

 public static function instance_config_form($mform)
 {
  if (method_exists("context_system", "instance"))
    $context = context_system::instance(0, IGNORE_MISSING, true);
  else
    $context = get_context_instance(CONTEXT_SYSTEM);

  if (has_capability('moodle/site:config', $context))
  {
   global $CFG;

   $mform->addElement('text', 'api_url', get_string('api_url', 'repository_helix_media_lib'), array('size'=>70));
   $mform->addElement('static', null, '', get_string('api_url_help', 'repository_helix_media_lib'));
   $mform->addRule('api_url', get_string('required'), 'required', null, 'client');

   $mform->addElement('text', 'http_host', get_string('http_host', 'repository_helix_media_lib'), array('size'=>70));
   $mform->addElement('static', null, '', get_string('http_host_help', 'repository_helix_media_lib'));
   $mform->addRule('http_host', get_string('required'), 'required', null, 'client');

   $mform->addElement('text', 'vstream_url', get_string('vstream_url', 'repository_helix_media_lib'), array('size'=>70));
   $mform->addElement('static', null, '', get_string('vstream_url_help', 'repository_helix_media_lib'));
   $mform->addRule('vstream_url', get_string('required'), 'required', null, 'client');

   $mform->addElement('text', 'astream_url', get_string('astream_url', 'repository_helix_media_lib'), array('size'=>70));
   $mform->addElement('static', null, '', get_string('astream_url_help', 'repository_helix_media_lib'));
   $mform->addRule('astream_url', get_string('required'), 'required', null, 'client');

   /**Uncomment this and comment out the array below if you want ld/hd options**
   $vopts=array(
     'lo' => get_string('video_quality_low', 'repository_helix_media_lib'),
     'hi' => get_string('video_quality_high', 'repository_helix_media_lib'),
     'ld' => get_string('video_quality_ld', 'repository_helix_media_lib'),
     'hd' => get_string('video_quality_hd', 'repository_helix_media_lib')
   );
   **/

   $vopts=array(
     'lo' => get_string('video_quality_low', 'repository_helix_media_lib'),
     'hi' => get_string('video_quality_high', 'repository_helix_media_lib')
   );

   $mform->addElement('select', 'video_quality', get_string('video_quality', 'repository_helix_media_lib'), $vopts);
   $mform->addElement('static', null, '', get_string('video_quality_help', 'repository_helix_media_lib'));

   $mform->addElement('text', 'v_width', get_string('v_width', 'repository_helix_media_lib'), array('size'=>4, 'value'=>320));
   $mform->addElement('static', null, '', get_string('v_width_help', 'repository_helix_media_lib'));

   $mform->addElement('text', 'v_height', get_string('v_height', 'repository_helix_media_lib'), array('size'=>4, 'value'=>260));

   $exists=false;
    $repoid=$mform->_elements[0]->_attributes["value"];
    if ($repoid>0)
     $exists=true;

   if (method_exists($mform, "setType"))
   {
     $mform->setType('api_url', PARAM_URL);
     $mform->setType('http_host', PARAM_URL);
     $mform->setType('vstream_url', PARAM_TEXT);
     $mform->setType('astream_url', PARAM_URL);
     $mform->setType('video_quality', PARAM_TEXT);
     $mform->setType('v_width', PARAM_INT);
     $mform->setType('v_height', PARAM_INT);
   }

   if ($exists)
   {
    $mform->addElement('static', null, '', get_string('v_height_help', 'repository_helix_media_lib')."<br /><br />".
     get_string('cat_link1', 'repository_helix_media_lib').
     " <a style='font-weight:bold;' href='".$CFG->wwwroot."/repository/helix_media_lib/editcats.php?repoid=".$repoid."'>".
     get_string('cat_link2', 'repository_helix_media_lib')."</a> ".
     get_string('cat_link3', 'repository_helix_media_lib').
     "<br /><br /><div style='text-align:center;'>".
     " <a style='font-weight:bold;font-size:medium;border:1px solid #000000;margin:2px;padding:2px;' href='".$CFG->wwwroot."/repository/helix_media_lib/editcats.php?repoid=".$repoid."'>".
     get_string('cat_link2', 'repository_helix_media_lib')."</a> ".
     "</div> ");
   }
   else
   {
    $mform->addElement('static', null, '', get_string('v_height_help', 'repository_helix_media_lib')."<br /><br />".
     get_string('cat_link1', 'repository_helix_media_lib')." ".
     get_string('cat_link2', 'repository_helix_media_lib')." ".
     get_string('cat_link3', 'repository_helix_media_lib'));
   }
  }
  else
  {
   $mform->addElement('static', null, '',  get_string('nopermissions', 'error', get_string('configplugin', 'repository_helix_media_lib')));
   return false;
  }
 }
}
