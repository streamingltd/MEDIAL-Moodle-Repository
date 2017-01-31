<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head><title>MEDIAL soap call tester</title></head>
<body>
<h1>MEDIAL SOAP Test page</h1>
<?php

/**
* This page tests the calls in the helixlib.php
* @author Tim Williams (tmw@autotrain.org)
* @package    repository
* @subpackage helix_media_lib
**/

    require_once("../../config.php");
    require_once("helixlib.php");
    require_once("../lib.php");
    global $CFG;

    /*****End of defaults*****/

    require_login(1);
    $context=context_system::instance();
    if (!has_capability('moodle/site:config', $context))
    {
     ?>
     <p>You do not have permission to do this.</p>
     </body>
     </html>
     <?php
     die;
    }

    if (array_key_exists('action', $_POST))
    {
     $id=intval($_POST['repoid']);

     $repo=repository::get_instance($id);
     $hml_soap=$repo->hml_soap;

     if ($_POST['action']=="cat")
      show_cat_content($hml_soap->get_all_categories(true));
     else
     if ($_POST['action']=="search")
      show_search_content($hml_soap->search_media($_POST['keywords'], $_POST['categoryId'], $_POST['contrib'], $_POST['filename'], true));
     else
     if ($_POST['action']=="getmedia")
      show_media_content($hml_soap->get_media_with_token($_POST['id'], $_POST['ip']));
    }

    $params = array(
       'context'=>array($context),
       'currentcontext'=>$context,
       'onlyvisible'=>true,
       'type'=>'helix_media_lib');
    $repolist = repository::get_instances($params);
    $repos="<select name='repoid'>";
    foreach ($repolist as $r)
    {
     $repos.="<option value='".$r->id."'>".$r->name."</option>";
    }
    $repos.="</select>";

    /**
    * Prints out the seach results
    * @param array $data The results
    **/

    function show_search_content($data)
    {
     echo "<hr /><h2>Search Results:</h2>";
     foreach ($data as $d)
     {
      echo "<p>";
      foreach ($d as $k=>$p)
      {
       echo $k."=".$p."<br />";
      }
      echo "</p>";
     }
    }

    /**
    * Prints out a media object
    * @param object $data The media object
    **/

    function show_media_content($data)
    {
     echo "<hr /><h2>Get Media Result:</h2><p><b>Details:</b><br />";
     foreach ($data->details as $k=>$p)
      echo $k."=".$p."<br />";
     echo "</p>";

     echo "<p><b>Token:</b><br />";
     foreach ($data->token as $k=>$p)
      echo $k."=".$p."<br />";
     echo "</p>";
    }

    /**
    * Prints out the category list
    * @param array The categories
    **/

    function show_cat_content($data)
    {
     echo "<hr /><h2>Categories Result:</h2><p>";
     foreach ($data as $k=>$p)
      echo $p->name.":".$p->label."<br />";
     echo "</p>";
    }

?>

<hr />

<h2>Categories</h2><p>
<form action="soap_test.php" method="post">
Repository : <?php echo $repos;?><br /><br />
<input type="hidden" name="action" value="cat" />
<input type="submit" value="Get all Categories" />
</form></p>

<br />
<h2>Search</h2>

<form action="soap_test.php" method="post">
<input type="hidden" name="action" value="search" />
<table>
 <tr><td>Repository </td><td><?php echo $repos;?></td></tr>
 <tr><td>Keywords </td><td><input type="text" name="keywords" /></td></tr>
 <tr><td>Category ID</td><td><input type="text" name="categoryId" /></td></tr>
 <tr><td>Contributor</td><td><input type="text" name="contrib" /></td></tr>
 <tr><td>File name</td><td><input type="text" name="filename" /></td></tr>
</table>
<input type="submit" value="Search" />
</form>

<br />
<h2>Get Media with Token</h2>

<form action="soap_test.php" method="post">
<input type="hidden" name="action" value="getmedia" />
<table>
 <tr><td>Repository </td><td><?php echo $repos;?></td></tr>
 <tr><td>Media ID </td><td><input type="text" name="id" /></td></tr>
 <tr><td>IP Address</td><td><input type="text" name="ip" /></td></tr>
</table>
<input type="submit" value="Get Media" />
</form>

</body>
</html>
