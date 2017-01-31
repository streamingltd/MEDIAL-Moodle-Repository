<?php
    /**
    * Editing page for Category associations
    * @author Tim Williams (tmw@autotrain.org)
    * @package    repository
    * @subpackage helix_media_lib
    **/

    require_once('../../config.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/tablelib.php');
    require_once('helixlib.php');

    global $OUTPUT, $PAGE, $DB;

    $repoid=optional_param("repoid", -1, PARAM_INT); 
    $update=optional_param("update", 0, PARAM_INT);

    if (method_exists("context_system", "instance"))
        $context = context_system::instance(0, IGNORE_MISSING, true);
    else
        $context = get_context_instance(CONTEXT_SYSTEM);

    $PAGE->set_context($context);

    $adminroot = admin_get_root();
    admin_externalpage_setup('managerepositories');

    echo $OUTPUT->header();

    echo "<h2 style='text-align:center;'>".get_string("cat_editor", "repository_helix_media_lib")."</h2><br />";

    if (!has_capability('moodle/site:config', $context))
    {
        echo '<p>'.get_string("not_authorised", "repository_helix_media_lib").'</p>';
        echo $OUTPUT->footer();
        die();
    }

?>

<p><?php print_string("all_cat_note", "repository_helix_media_lib"); ?></p>
<br />
<form action="<?php echo $CFG->wwwroot; ?>/repository/helix_media_lib/editcats.php" method="get">
<table style="margin-left:auto;margin-right:auto;"><tr>
<td><?php print_string("choose_repo", "repository_helix_media_lib"); ?></td>
<td><select name='repoid'>
<?php
    $params = array(
       'context'=>array($context),
       'currentcontext'=>$context,
       'onlyvisible'=>true,
       'type'=>'helix_media_lib');
    $repolist = repository::get_instances($params);
    foreach ($repolist as $r)
    {
     if ($repoid==-1)
         $repoid=$r->id;
     $sel="";
     if ($repoid==$r->id)
         $sel=" selected='selected'";
     echo "<option value='".$r->id."'".$sel.">".$r->name."</option>";
    }

?>
</select></td>
<td><input type="submit" value="<?php print_string("choose_repo_submit", "repository_helix_media_lib"); ?>" /></td>
</tr></table>
</form>

<?php
    $repo=repository::get_instance($repoid);
    $hml_soap=$repo->hml_soap;
    if ($repo==null || $hml_soap==null)
    {
        echo "<p>".get_string("bad_repo", "repository_helix_media_lib")."</p>";
        echo $OUTPUT->footer();
    }

    if ($update)
        update_hml_cats($hml_soap);
?>

<form action="<?php echo $CFG->wwwroot; ?>/repository/helix_media_lib/editcats.php" method="post">
<table style="margin-left:auto; margin-right:auto;"><tr><td>
<?php

    $hml_cats=$hml_soap->get_all_categories_unfiltered(true);
    $hml_cats[0]->label=get_string("allow_all_cat", "repository_helix_media_lib");;

    $tablecolumns = array('moodlecat', 'inherit', 'htmlcat');
    $tableheaders = array(get_string('moodlecat', "repository_helix_media_lib"), get_string("inherit", "repository_helix_media_lib"),
     get_string('hmlcat', "repository_helix_media_lib"));

    $table = new flexible_table('respository-helix_media_lib-editcats');

/// define table columns, headers, and base url
    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    $table->define_baseurl($CFG->wwwroot.'/repository/helix_media_lib/editcats.php');

/// table settings
    $table->sortable(false);
    $table->initialbars(true);
    $table->pageable(false);

/// set attributes in the table tag
    $table->set_attribute('cellpadding', '4');
    $table->set_attribute('id', 'editcats');
    $table->set_attribute('class', 'generaltable generalbox');
    $table->set_attribute('style', 'margin-left:auto; margin-right:auto;');
    $table->setup();

    $map_rec=$hml_soap->get_mapping_record(0);
    $table->add_data(array(
        get_string("system_default","repository_helix_media_lib"),
        "<input type='hidden' name='map_id_0' value='".$map_rec->id."' />".
        "<input type='hidden' name='inherit_0' value='0' />\n",
        "<select name='hml_cats_0[]' multiple='multiple' size='4'>".get_hml_cats($hml_cats, $map_rec->hmlcat)."</select>"));

/// SQL
    $sql = "SELECT * FROM {$CFG->prefix}course_categories ORDER BY path ASC";

    $rec_list=$DB->get_records_sql($sql);
    $script="";
    $cat_ids="0";
    $maincat_id="0";
    $subcount=0;
    $cat_styles="";

    foreach ($rec_list as $r)
    {
        $map_rec=$hml_soap->get_mapping_record($r->id);

        /**Create the category name**/
        $cats=explode("/", $r->path);
        $name="";
        foreach ($cats as $c)
            if (strlen($c)>0)
                $name=$name."/".$rec_list[$c]->name;

        /**Set up the selected HML categories**/

        $tdata=array();
        if (count($cats)<3)
        {
            if ($subcount==0 && $maincat_id!=0)
                $script.="document.getElementById('subcat_m_".$maincat_id."').innerHTML='';\n";

            $subcount=0;
            $maincat_id=$r->id;
            $tdata[]=$name."<div class='subh' id='subcat_m_".$r->id."'>".
            "<a href=\"javascript:show_subcat(".$r->id.");\"><b>&rArr;</b> ".
            get_string("show_subcats", "repository_helix_media_lib")."</a></div>";
            $cat_styles.=".catrow_".$r->id." {display:none;}\n";
        }
        else
        {
            $subcount++;
            $tdata[]=$name;
        }

        $tdata[]="<input type='hidden' name='map_id_".$r->id."' value='".$map_rec->id."' />".
            "<input style='margin-left:40px;' type='checkbox' id='inherit_id_".$r->id."' ".
            "name='inherit_".$r->id."' ".$map_rec->checked." onclick='cb_tick(".$r->id.");' />\n";

        $tdata[]="<select id='hml_cats_id_".$r->id."' name='hml_cats_".$r->id."[]' multiple='multiple' size='4'>".
            get_hml_cats($hml_cats, $map_rec->hmlcat)."</select>\n";

        if (count($cats)<3)
            $table->add_data($tdata);
        else
            $table->add_data($tdata, "catrow_".$maincat_id);
        
        $ih="inline";
        if ($map_rec->inherit)
            $ih="none";

        //$script.=" document.getElementById('hml_cats_id_".$r->id."').style.display=".$ih.";\n";
        $cat_styles.="#hml_cats_id_".$r->id." {display:".$ih.";}\n";
        $cat_ids.=",".$r->id;
    }
    $table->print_html();
?>
</td></tr><tr><td align="right">
    <input type="hidden" name="repoid" value="<?php echo $repoid;?>" />
    <input type="hidden" name="cat_ids" value="<?php echo $cat_ids; ?>" />
    <input type="hidden" name="update" value="1" />
    <input type="submit" value="<?php print_string("update", "repository_helix_media_lib"); ?>" />
</td></tr></table>
<style type="text/css">
<?php echo $cat_styles; ?> 

.subh
{
 margin-top:26px;
}
</style>
</form>

<script type="text/javascript">
//<!--

if (typeof(document.getElementsByClassName)=="undefined")
{
 document.getElementsByClassName = function(class_name)
 {
  var docList = this.all || this.getElementsByTagName('*');
  var matchArray = new Array();

  var re = new RegExp("(?:^|\\s)"+class_name+"(?:\\s|$)");
  for (var i = 0; i < docList.length; i++)
  {
   if (re.test(docList[i].className) )
   {
    matchArray[matchArray.length] = docList[i];
   }
  }
  return matchArray;
 }
}

 var isIE=false;
 var browserVersion=0;

 if (navigator.userAgent.toLowerCase().indexOf("msie")>-1)
 {
  isIE=true;
  readBrowserVersion(userAgent, "msie ", ";");
 }

 function readBrowserVersion(userAgent, keyA, keyB)
 {
  var indexA=userAgent.indexOf(keyA)+keyA.length;
  if (keyB.length>0)
  {
   var indexB=userAgent.indexOf(keyB, indexA);
   browserVersion=parseFloat(userAgent.substring(indexA, indexB));
  }
  else
   browserVersion=parseFloat(userAgent.substring(indexA));
 }

 function cb_tick(id)
 {
  var cb=document.getElementById("inherit_id_"+id);
  var select=document.getElementById("hml_cats_id_"+id);
  if (cb.checked)
   select.style.display="none";
  else
   select.style.display="inline";
 }

 function show_subcat(id)
 {
  document.getElementById("subcat_m_"+id).innerHTML=
   "<a href=\"javascript:hide_subcat("+id+");\"><b>&dArr;</b> <?php echo get_string("hide_subcats", "repository_helix_media_lib"); ?></a>";

  var rows=document.getElementsByClassName("catrow_"+id);
  var dsp="table-row";
  if (isIE && browserVersion<9)
   dsp="inline";
  for (var l=0; l<rows.length; l++)
    rows[l].style.display=dsp;
 }

 function hide_subcat(id)
 {
  document.getElementById("subcat_m_"+id).innerHTML=
   "<a href=\"javascript:show_subcat("+id+");\"><b>&rArr;</b> <?php echo get_string("show_subcats", "repository_helix_media_lib"); ?></a>";

  var rows=document.getElementsByClassName("catrow_"+id);
  for (var l=0; l<rows.length; l++)
  {
   rows[l].style.display="none";
  }

 }

 function getRule(sheets, rule)
 {
  for(var loop=sheets.length-1; loop>-1; loop--)
  {
   var rules=sheets[loop].cssRules? sheets[loop].cssRules: sheets[loop].rules;
   for (i=0; i<rules.length; i++)
   {
    if(typeof(rules[i].selectorText)!="undefined" && rules[i].selectorText.toLowerCase()==rule)
    {
     return rules[i].style;
    }
   }
  } 

  return null;
 }


<?php echo $script;?>
//-->
</script>

<?php
    $OUTPUT->footer();

    /**
    * Gets the Media Library Categories as a String of HTML Options
    * @param array $hml_cats Array of the available HML Categories
    * @param array $selected_cats The currently selected categories
    **/

    function get_hml_cats($hml_cats, $selected_cats)
    {
        $hml_cat_opts="";
        foreach ($hml_cats as $p)
        {
            $selected="";
            foreach ($selected_cats as $cs)
            {
                if ($cs==$p->name)
                {
                    $selected=" selected='selected'";
                    break;
                }
            }

            $hml_cat_opts.=" <option value='".$p->name."'".$selected.">".$p->label."</option>\n";
        }

        return $hml_cat_opts;
    }

   /**
   * Updates the mapping records for the HML>Moode category associations
   * @param soap_helix_media_lib $hml_soap The HML Soap client
   **/

    function update_hml_cats($hml_soap)
    {
        $cat_ids=explode(",", required_param("cat_ids", PARAM_SEQUENCE));
        foreach($cat_ids as $ci)
        {
            $map_id=required_param("map_id_".$ci, PARAM_INT);
            $inherit=optional_param("inherit_".$ci, 0, PARAM_BOOL);
            $hmlcat=optional_param_array("hml_cats_".$ci, -1, PARAM_INT);
            $hml_soap->update_mapping_record($map_id, $ci, $inherit, $hmlcat);
        }
    }
?>
