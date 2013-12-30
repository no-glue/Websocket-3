<!DOCTYPE HTML>
<html>
<head>
	<?php
		//enter your host address and server_socket.php place
		$server_socket = '192.168.56.101:24568/server_socket.php';
	?>
	<style type="text/css">
		#ping_window{
			display:none;
		}
	</style>
	<script>
	var socket;
	var ping_string ="";
		function ping_innerChange(){
			document.getElementById("ping_string").innerHTML = ping_string;

		}
		function ping_close(){
			ping_string = "";
			ping_innerChange();
			socket.send("500");
		}

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
 		function ping_on_Open(evt){
 			ping_string = "<p>Welcome to use PING!</p>";
 			socket.send("ping"+document.getElementById("dest_IP").value);
 			ping_innerChange();
 		}
 		function on_Close(evt) { 
 			socket.send("close"); 
 		} 
 		function on_Message(evt) { 
 			ping_string += "<p>" + evt.data + "</p>";
 			ping_innerChange();
 		} 
 		function ping_on_Message(evt) {
 			ping_string += "<p>" + evt.data + "</p>";
 			ping_innerChange();
 			//socket.send("200");
 		}
 		function on_Error(evt) { 
 			alert('Error occured: ' + evt.data); 
 		}
		function testSocket(host, action)
		{
			var master = "ws://"+host;
			socket = new WebSocket(master);
			if(action =="ping"){
				socket.onopen = function (evt) { ping_on_Open(evt); }; 
 				socket.onclose = function (evt) { on_Close(evt) }; 
 				socket.onmessage = function (evt) { ping_on_Message(evt) }; 
 				socket.onerror = function (evt) { on_Error(evt) }; 
 			}
 		}
 		function testReady()
 		{
 			alert(socket.readyState);
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
			<button type='button' value='PING!' onclick='testSocket("<?php echo $server_socket?>","ping")'>PING!</button>
		</form>
		<div id='ping_content'>
			<p id='ping_string'>Ping:</p>
			<button type='button' value='CLOSE' onclick='ping_close()'>CLOSE</button>
		</div>
		<a href='#' onclick="go_back_ping()">Go Back</a>
	</div>
	<a href='#' onclick="testReady()">Test</a>
</body>
</html>