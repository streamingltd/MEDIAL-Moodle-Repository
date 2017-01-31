<?php

/*********************Some help with turning debug on/off***********************************/

define ("HELIX_IS_DEBUGGING", false);

if (!class_exists("nusoap_base"))
 require_once("lib/nusoap.php");

/**
 * SOAP Protocol library for Helix Media Library Plugin
 *
 * @since 2.0
 * @package    repository
 * @subpackage helix_media_lib
 * @author     Tim Williams <tmw@autotrain.org> on behalf of Streaming LTD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class soap_helix_media_lib
{

 /**
 * Constructor for the Protocol library
 * @param string $su The remote SOAP server URL
 * @param integer $id The Moodle ID of this repository
 * @param string $course The current course object
 **/

 public function __construct($su, $id, $course)
 {
  $this->soap_url=$su;
  $this->id=$id;
  $this->create_client();
  $this->course=$course;
 }

 /**
 * This method creates a suitable SOAP client
 **/

 private function create_client()
 {
  global $CFG;

  if (HELIX_IS_DEBUGGING)
   echo 'Create SOAP Client '.$this->soap_url.'?wsdl<br />';

  $client=new nusoap_client($this->soap_url.'?wsdl', true);
  if (!empty($CFG->proxyhost))
  {
   $pp=false;
   if (!empty($CFG->proxyport))
    $pp=$CFG->proxyport;

   $pu=false;
   $ps=false;
   if (!empty($CFG->proxyuser) && !empty($CFG->proxypassword))
   {
    $pu=$CFG->proxyuser;
    $ps=$CFG->proxypassword;
   }
   if (HELIX_IS_DEBUGGING)
    echo "proxy host:".$CFG->proxyhost." port:".$pp." user:".$pu." pass:".$ps;

   $client->setHTTPProxy($CFG->proxyhost, $pp, $pu, $ps);
  }
  $err = $client->getError();

  if (HELIX_IS_DEBUGGING)
   echo 'Done SOAP client<br />';

  if ($err)
  {
   $this->show_error("SOAP Constructor error", $err."<br /><br />client->response='".$client->response."'");
   $this->err=$err;
   return;
  }

  $client->soap_defencoding="utf-8";

  $this->client=$client;
  $this->proxy=$client->getProxy();
  $this->err=false;

  if (!$this->proxy)
  {
   $this->err=true;
   $this->show_error("API Error", get_string("api_error", "repository_helix_media_lib"));
  }
 }

 /**
 * Gets a list of all the categories of media held on the HML server without category filtering
 * @param boolean $showerror true if you want the error messages to be displayed
 * @return array An array of categories, suitably formated for use in the file picker
 **/

 public function get_all_categories_unfiltered($showerror=false)
 {
  $result=$this->send_soap(array(), "GetAllCategories", $showerror);

  if ($result==null)
  {
   $result=array();
   $result[0]=new stdClass();
   $result[0]->type='option';
   $result[0]->name='0';
   $result[0]->label=get_string("cat_error", "repository_helix_media_lib");
   return $result;
  }
  else
  {
   $items=array();

   $i=new stdClass();
   $i->type='option';
   $i->name=0;
   $i->label=get_string("all_cat", "repository_helix_media_lib");
   $items[]=$i;

   /**Create items with the correct structure for the filepicker**/
   if (array_key_exists("CategoryId", $result['GetAllCategoriesResult']['Category']))
     $items[]=$this->read_cat_item($result['GetAllCategoriesResult']['Category']);
   else
   {
    foreach ($result['GetAllCategoriesResult']['Category'] as $cat)
    {
     $items[]=$this->read_cat_item($cat);
    }
   }
   return $items;
  }
 }

 /**
 * Gets a list of all the categories of media held on the HML server
 * @param boolean $showerror true if you want the error messages to be displayed
 * @return array An array of categories, suitably formated for use in the file picker
 **/

 public function get_all_categories($showerror=false)
 {
  $allcats=$this->get_all_categories_unfiltered($showerror);

  if (method_exists("context_course", "instance"))
    $course_context = context_course::instance($this->course->id);
  else
    $course_context=get_context_instance(CONTEXT_COURSE, $this->course->id);

  if (has_capability('repository/helix_media_lib:searchall', $course_context))
   return $allcats;

  $allowed=$this->get_allowed_categories();
  $filtered=array();
  foreach ($allcats as $c)
  {
   if ($this->cat_is_allowed($allowed, $c->name))
    $filtered[]=$c;
  }

  return $filtered;
 }

 /**
 * Checks if the specified category is present in the list of alloed categories
 * @param array $allowed The list of allowed categories
 * @param integer $totest The category to test
 * @return boolean true if the category is allowed
 **/

 private function cat_is_allowed($allowed, $totest)
 {
  foreach ($allowed as $a)
  {
   if ($a==$totest)
    return true;
  }

  return false;
 }

 /**
 * Reads a single category item
 * @param array $cat The category item to read
 * @result object The processed result
 **/

 private function read_cat_item($cat)
 {
  $i=new stdClass();
  $i->type='option';
  $i->name=$cat["CategoryId"];
  $i->label=$cat["CategoryName"];
  return $i;
 }

 /**
 * This method searches the HML server using the specified details
 * @param string $keywords The search keywords
 * @param integer $cat_id The category ID
 * @param string $contrib The contributors name
 * @param string $filename The file name
 * @param boolean $showerror true if you want the error messages to be displayed
 * @return array The search results as an array
 **/

 public function search_media($keywords, $cat_id, $contrib, $filename, $showerror=false)
 {
  if (!is_number($cat_id))
   $cat_id=0;

  if (method_exists("context_course", "instance"))
    $course_context = context_course::instance($this->course->id);
  else
    $course_context=get_context_instance(CONTEXT_COURSE, $this->course->id);

  if (!has_capability('repository/helix_media_lib:searchall', $course_context))
  {
   $acats=$this->get_allowed_categories();
   if (!$this->cat_is_allowed($acats, $cat_id))
    $cat_id=current($acats);
  }

  $params=array("keywords"=>$keywords, "categoryId"=>$cat_id, "contributor"=>$contrib, "fileName"=>$filename, 
   "userId"=>0);
  $result=$this->send_soap($params, "SearchMedia", $showerror);

  if ($result==null)
  {
   $i=array();
   $i['title']=get_string('search_error', 'repository_helix_media_lib');
   return array($i);
  }
  else
  {
   $items=array();
   /***This needs to be handled differently if only one result is returned. Detect this by checking for the Video element***/
   if (array_key_exists("Video", $result["SearchMediaResult"]["MediaListing"]))
    $items[]=$this->read_medialisting_item($result["SearchMediaResult"]["MediaListing"]);
   else
   {
    foreach ($result["SearchMediaResult"]["MediaListing"] as $mi)
     $items[]=$this->read_medialisting_item($mi);
   }
   return $items;
  }
 }

 /**
 * This method reads a single MediaListing from a search result and creats a new item with the
 * correct array keys for the Moodle file picker
 * @param array $item The element to process
 * @return array The processed information 
 **/

 private function read_medialisting_item($item)
 {
  global $CFG;
  $i=array();

  if (!is_array($item))
  {
   echo "Not an array. ".$item;
   return null;
  }

  $i['description']=$item["Video"]["Description"];
  $i['thumbnail_title']=$item["Video"]["Description"];
  $i['title']=$item["Video"]["Title"].": ".$item["Video"]["Description"];
  $i['shorttitle']=$item["Video"]["Title"].": ".$item["Video"]["Description"];
  $i['source']=$CFG->wwwroot."/repository/helix_media_lib/view.php?rid=".$this->id."&amp;mid=".$item["Video"]["VideoId"];

  /**Extra meta data for MDL23+**/
  $i['author']=get_string('not_available', 'repository_helix_media_lib');
  //$i['datemodified']=1201305122;
  //$i['datecreated']=1201304012;
  $i['license']=get_string('not_available', 'repository_helix_media_lib');
  //$i['size']=10000;

  if (array_key_exists("ThumbnailUrl", $item))
   $i['thumbnail']=$item["ThumbnailUrl"];
  else
   $i['thumbnail']=$CFG->wwwroot."/repository/helix_media_lib/images/helix.jpg";

  //$i['thumbnail_width']=105;
  //$i['thumbnail_height']=66;

  $i['thumbnail_width']=320;
  $i['thumbnail_height']=204;

  return $i;
 }

 /**
 * This method reads a single MediaItem from a search result and creats a new item with the
 * correct array keys for the Moodle file picker
 * @param array $item The element to process
 * @return array The processed information 
 **/

 private function read_media_item($item)
 {
  global $CFG;
  $i=array();

  $i['description']=$item["Description"];
  $i['title']=$item["Title"];
  $i['source']=$CFG->wwwroot."/repository/helix_media_lib/view.php?rid=".$this->id."&amp;mid=".$item["VideoId"];
  $i['videoid']=$item["VideoId"];

  if (array_key_exists("ThumbnailUrl", $item))
   $i['thumbnail']=$item["ThumbnailUrl"];
  else
   $i['thumbnail']=$CFG->wwwroot."/repository/helix_media_lib/images/helix.jpg";

  //$i['thumbnail_width']=105;
  //$i['thumbnail_height']=66;

  $i['thumbnail_width']=320;
  $i['thumbnail_height']=204;

  return $i;
 }

 /**
 * This method performs a SOAP call to retrieve the specified media with a one time access code
 * @param integer $mediaId The ID of the media to retrieve
 * @param string $ip The IP address of the system attempting to view the media
 * @return object The processed media information, ->details contains the media information,
 *         ->token contains the access key and associated information.
 **/

 public function get_media_with_token($mediaId, $ip)
 {
  $params=array("mediaId"=>$mediaId, "ipAddress"=>$ip);
  $result=$this->send_soap($params, "GetSecureMediaListing");

  if ($result==null)
   return false;
  else
  {
   /***Read the response here***/
   $r=new stdclass;
   $r->details=$this->read_media_item($result["GetSecureMediaListingResult"]["Video"]);
   $r->details['thumbnail']=$result["GetSecureMediaListingResult"]["ThumbnailUrl"];
   $r->details['filename']=$result["GetSecureMediaListingResult"]["FileName"];
   $r->details['mediatype']=$this->get_media_type($result);
   $r->token=$result["GetSecureMediaListingResult"]["Token"];

   return $r;
  }
 }

 /**
 * Determins the media type from the 
 * @param boolean $result The details result
 * @return string audio or video
 **/

 private function get_media_type($result)
 {
  if ($result["GetSecureMediaListingResult"]['Video']['IsAudio']=="false")
   return "video";
  else
   return "audio";
 }

 /**
 * Sends a SOAP request using a nuSoap proxy class
 * @param string $params The parameters to insert into the request
 * @param string $action The SOAP method to call
 * @param boolean $showerror If error messages should be printed
 * @return array a keyed arrray containing the server response
 **/

 private function send_soap($params, $action, $showerror=true)
 {
  if ($this->err!=false)
   return null;

  $result=eval('return $this->proxy->'.$action.'($params);');

  if (HELIX_IS_DEBUGGING)
  {
   echo 'Sending <br /><textarea cols="100" rows="20">'.$this->proxy->request.'</textarea><br />';
   echo 'Response <br /><textarea cols="100" rows="20">'.$this->proxy->response.'</textarea><br />';
  }

  if ($this->proxy->fault)
  {
   if ($showerror)
    $this->show_error(get_string('soap_fault', 'repository_helix_media_lib'), $result, get_string('soap_fault_help', 'repository_helix_media_lib'));

   return null;
  }
  else
  {
   $err = $this->proxy->getError();
   if ($err)
   {
    if ($showerror)
     $this->show_error(get_string('send_error', 'repository_helix_media_lib'), $err, get_string('send_error_help', 'repository_helix_media_lib'));

    return null;
   }
  }

  if (array_key_exists("Fault", $result))
  {
   if ($showerror)
    $this->show_error($result["Fault"]["faultcode"], $result["Fault"]["faultstring"]);
   return null;
  }

  return $result;
 }

 /**
 * Gets the category mapping data for the specified Moodle category
 * @param integer moodlecat The moodle category to update
 * @return object The processed category map
 **/

 public function get_mapping_record($moodlecat)
 {
  global $DB;
  $map_rec=$DB->get_record("repository_helix_media_lib_c", array("repoid"=>$this->id , "moodlecat"=>$moodlecat));
  if ($map_rec!=null)
  {
   if ($map_rec->inherit==1)
    $map_rec->checked="checked='checked'";
   else
    $map_rec->checked="";
   $map_rec->hmlcat=explode(",", $map_rec->hmlcat);
  }
  else
  {
   $map_rec=new stdclass;
   if ($moodlecat>0)
    $map_rec->inherit=1;
   else
    $map_rec->inherit=0;
   $map_rec->checked="checked='checked'";
   $map_rec->hmlcat=array();
   $map_rec->id=-1;
  }
  return $map_rec;
 }

 /**
 * Gets the list of categories which are mapped to the current course
 * @return array the category list
 **/

 public function get_allowed_categories()
 {
  $map_rec=$this->get_inherited_mapping($this->course->category);
  if ($map_rec==null)
   return array();

  return explode(",",$map_rec->hmlcat);
 }

 /**
 * Recurses back through the category tree to find the first valid set of mapping data
 * @param integer $cat The category ID to start at
 * @return array The mapping data as an array
 **/

 public function get_inherited_mapping($cat)
 {
  global $DB;
  $map_rec=$DB->get_record("repository_helix_media_lib_c", array("repoid"=>$this->id , "moodlecat"=>$cat));
  if ($map_rec==null || $map_rec->inherit)
  {
   $thiscat=$DB->get_record("course_categories", array("id"=>$cat));
   if ($thiscat->parent>0)
    return $this->get_inherited_mapping($thiscat->parent);
   else
    return $DB->get_record("repository_helix_media_lib_c", array("repoid"=>$this->id , "moodlecat"=>0));
  }
  else
   return $map_rec;

  return null;
 }

 /**
 * Updates the category mapping record for a specified Moodle category
 * @param integer $map_id The id of the category mapping record
 * @param integer $ci The Moodle category id
 * @param booolean $inherit true id this category inherits it's mapping from a super category
 * @param integer $hmlcat The HML Category ID
 **/

 public function update_mapping_record($map_id, $ci, $inherit, $hmlcat)
 {
  global $DB;
  $rec=new stdclass;
  $rec->inherit=$inherit;
  if ($hmlcat==-1)
   $rec->hmlcat="";
  else
   $rec->hmlcat=implode(",", $hmlcat);

  $rec->moodlecat=$ci;
  $rec->repoid=$this->id;

  if ($map_id>-1)
  {
   $rec->id=$map_id;
   $DB->update_record("repository_helix_media_lib_c", $rec);
  }
  else
   $DB->insert_record("repository_helix_media_lib_c", $rec);
 }

 /**
 * Shows an error on the page
 * @param string $title The error title
 * @param string $err The error message
 * @param string $help An additional help message
 **/

 private function show_error($title, $err, $help="")
 {
  echo "<div class='generalbox error'>";
  echo "<p>".get_string('hml_error', 'repository_helix_media_lib')."</p>\n";
  echo "<h2>".$title."</h2>\n<p>".$err."</p>\n";
  if (strlen($help)>0)
   echo "<p>".$help."</p>\n";
  echo "</div>";
 }

 /**
 * Debugging helper method recussively displays array information
 * @param array $array The array to show
 * @param string $level The recursion level as a string of - symbols
 **/

 function showArray($array, $level='')
 {
  foreach ($array as $k=>$p)
  {
   if (gettype($p)=="array")
   {
    echo $k."<br />";
    $this->showArray($p, $level."-");
   }
   else
    echo $level.$k.':'.$p.'<br />';
  }
 }

}
?>
