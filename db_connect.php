<?php 
	// connect to the database
	$conn = mysqli_connect('localhost', 'root', '', 'UmmisTracking');
	// check connection
	if(!$conn){
		echo 'Connection error: '. mysqli_connect_error();
	}
?>	
