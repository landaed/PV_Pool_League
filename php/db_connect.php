<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'pvd001_eli');
define('DB_PASSWORD', '>ZJkJ9i,)&FkD4M');
define('DB_DATABASE', 'pvd001_new_pvd');
$db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if($db === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>
