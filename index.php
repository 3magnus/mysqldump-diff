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
	$obj = new MySQLdumpDiff();
   $obj->File1 = 'old_backup.sql';
   $obj->File2 = 'new_backup.sql';
   $obj->File3 = 'diff_backup.sql';
   $obj->Options = array(2,2); // 1= with statistics per table , 2= without / 1= with CREATE statements , 2= without
   $obj->Export = 'print'; // or 'file'
   $obj->ProcessFiles();
	?>

</body>
</html>
              