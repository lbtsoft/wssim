<?php

// loads data from GET/POST vars into an HTML form and then passes them on to Moodle as POST vars

// GET/POST data expected:
//  username
//  password
//
// RETURNS:
//	Nothing

$host = $_SERVER['SERVER_NAME'];

if (isset($_GET['username']) && isset($_GET['password'])):
	$username=$_GET['username'];
	$password=$_GET['password'];
elseif (isset($_POST['Username']) && isset($_POST['Password'])):
	$username=$_POST['Username'];
	$password=$_POST['Password'];
endif;

if (isset($_GET['id']) && isset($_GET['userid'])):
	$id=$_GET['id'];
	$userid=$_GET['userid'];
elseif (isset($_POST['id']) && isset($_POST['userid'])):
	$id=$_POST['id'];
	$userid=$_POST['userid'];
endif;

//echo "<p>host=$host</p><p>username=$username</p><p>password=$password</p>";

if ($username && $password):
?>

<html>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<head></head>
<body>

<form name="login" method="post" action="http://<?php echo $host; ?>/login/index.php">
<input type="hidden" name="username" value="<?php echo $username ?>">
<input type="hidden" name="password" value="<?php echo $password ?>">
</form>

<?php if ($id && $userid): ?>
<form name="showgrades" method="get" action="http://<?php echo $host; ?>/grade/report/user/index.php?id=<?php echo $id ?>&userid=<?php echo $userid ?>">
</form>
<?php endif; ?>

<script language="JavaScript">

function Validate() {
	document.login.submit();
	<?php if ($id && $userid): ?>
	document.showgrades.submit();
	<?php endif; ?>
}

//console.log("auto_login.php: \nhost=<?=$host ?> \nusername=<?=$username?> \npassword=<?=$password?>");

Validate();

</script>

</body>
</html>

<?php endif; ?>
