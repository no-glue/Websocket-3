<!DOCTYPE HTML>
<html>
<head>
	<?php
		//enter your host address and server_socket.php place
		$server_socket = "localhost/server_socket.php";
	?>
	<style type="text/css">
		#ping_window{
			display:none;
		}
	</style>
	<script>
	var socket;

		function go_to_ping(){
			document.getElementById("ping_window").style.display="block";
		
		}
		function go_back_ping()
		{
			document.getElementById("ping_window").style.display="none";
		}
		function on_Open(evt) { 
 			alert("Connected!");
 			socket.send("hellooooo");
 		} 
 		function on_Close(evt) { 
 			alert(evt.code); 
 		} 
 		function on_Message(evt) { 
 			alert('Retrieved data from server: ' + evt.data); 
 		} 
 		function on_Error(evt) { 
 			alert('Error occured: ' + evt.data); 
 		}
		function testSocket(host)
		{
			var master = "ws://"+host;
			
			socket = new WebSocket(master);
			socket.onopen = function (evt) { on_Open(evt); }; 
 			socket.onclose = function (evt) { on_Close(evt) }; 
 			socket.onmessage = function (evt) { on_Message(evt) }; 
 			socket.onerror = function (evt) { on_Error(evt) }; 
 			socket.send(document.getElementById(dest_IP));

 		}
 		function testReady()
 		{
 			document.write(socket.readyState);
 		}
 		
			
	</script>
</head>
<body>

	<div id='action'>
		<a href='#' onclick="go_to_ping()">PING</a>
	</div>
	<div id='ping_window'>
		<form action='' method='post' name='address_box'>
			IP address:<input type='text' size=20 id='dest_IP'/><br/>
			<button type='button' value='PING!' onclick='testSocket("<?php echo $server_socket;?>")'>PING!</button>
		</form>
		<a href='#' onclick="go_back_ping()">Go Back</a>
	</div>
	<a href='#' onclick="testReady()">Test</a>
</body>
</html>