<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Untitled Document</title>
</head>
<?php
 require_once('mysqldumpdiff.class.php');
?>
<body>

	<?php
	$obj = new BackupDiff();
   $obj->File1 = 'bckp_tecnonet--usersdata.mysql';
   $obj->File2 = 'bckp_teste7--usersdata.mysql';
   $obj->File3 = 'diff_backup.mysql';
   $obj->Export = 'print'; // or 'download'
   $obj->ProcessFiles();
   //echo '<p>Files diff saved in '.$obj->File3.'</p>';
	?>

</body>
</html>
              