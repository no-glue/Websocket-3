<?php
//global variables
	$master = init();
	$users = array();
	$sockets = array($master);
	$receive_address = "unknown";
	while(true){
		usleep(400);
		$changed = $sockets;
		socket_select($changed, $w=NULL, $e=NULL, 0);
		foreach($changed as $socket){
			if($socket == $master){
				$client = socket_accept($master);
				create_newuser($client);
			}
			else{
				$bytes = @socket_recv($socket, $buf, 2048, 0);
					$user = get_user_by_socket($socket);
					if(!$user -> handshake)
						do_handshake($user,$buf);
				
					$buf = receiveMessage($buf);
					if($buf == 'CLOSE')
					{
						delete_user($socket);
						echo "\nDISCONNECTED\n";
					}
					process($user, $buf);//implement this process(DONE)
					//$buf syntax: order(4)+ip_addr(variable);	
			}
		}
		foreach ($users as $user) {
			if($user->order == null)
				continue;
			else{
				switch ($user->order) {
					case 'ping':
						# code...
						if(microtime(true) - $user->timestamp < 1)
							break;
						else{
							$tobe_sent = ping($user->ip_addr,'80');
							if($tobe_sent == false)
							{
								$user->order = '';
								sendMessage("FALSE_CONNECTION",$user->socket);
							}
							else
								sendMessage($tobe_sent,$user->socket);
								$user->timestamp = microtime(true);
						}
						break;
					case 'trac':
						# code...
						if($user->ttl == 0){
							$user->order = '';
							break;
						}
						if(microtime(true) - $user->timestamp < 1)
							break;
						else{
							sendMessage(traceroute($user),$user->socket);
							$user->timestamp = microtime(true);
						}
						break;	
					case 'path':
						if($user->ttl == 0){
							$user->order = '';
							break;
						}
						if(microtime(true) - $user->timestamp < 1)
							break;
						else{
							$result = pathping($user);
							if($result != false){
								echo $result;
								sendMessage($result,$user->socket);
								$user->timestamp = microtime(true);
							}
						}	
						break;	
					default:
						# code...
						break;
				}
			}
		}
	//	$client = handshake($self_socket);
	//	$receive = receiveMessage($client);// this is the order;
		//ping traceroute pathping
		//run the relative php
		//
	//	echo $receive."\n";
	//	sendMessage(ping("192.168.56.101"),$client);
	//	echo "CHECK1st:\n".$num."\n";
	//	sleep(12);
	//	$num = check_message($client);
	//	echo "CHECK:\n".$num."\n";
	}

	function create_newuser($socket){
		global $sockets, $users;
		$user = new User();
		$user -> id = uniqid();
		$user -> socket = $socket;
		array_push($users, $user);
		array_push($sockets, $socket);
	}
	function delete_user($socket){
		global $sockets, $users;
		$found = null;
		$n = count($users);
		for($i=0;$i<$n;$i++){
			if($users[$i]->socket == $socket){
				$found = $i;
				break;
			}
		}
		array_splice($users, $found, 1);
		$index = array_search($socket, $sockets);
		array_splice($sockets, $index,1);
		socket_close($socket);
		echo "CLOSEDSOCKET";
	}
	function get_user_by_socket($socket){
		global $users;
		$found = null;
		foreach($users as $user){
			if($user->socket == $socket){
				$found = $user;
				break;
			}
		}
		return $found;
	}
	function process($user, $buf){
		$temp_str = str_split($buf,4);
		$order = $temp_str[0];
		$n = count($temp_str);
		$ip_addr = "";
		for($i=1;$i<$n;$i++){
			$ip_addr .= $temp_str[$i];
		}
		$user->order = $order;
		$user->ip_addr = $ip_addr;
		$user->timestamp = microtime(true);
	}

	function asc2bin($temp){
		$len = strlen($temp);
		$bin_data="";
		for($i=0;$i<$len;$i++){
			$bin_data .= sprintf("%08b",ord(substr($temp, $i, 1)));
		}
		return $bin_data;
	}

	function bin2asc($temp){
		$len = strlen($temp);
		$str_data = "";
		for($i=0;$i<$len;$i=$i+8)
			$str_data .= chr(bindec(substr($temp, $i, 8)));
		return $str_data;
	}
	function myXOR($string_a,$string_b){
		$length = strlen($string_a); //default length a=b;
		$result = "";
		for($i=0;$i<$length;$i++){
			if($string_a[$i] == $string_b[$i]){
				$result = $result . "0";
			}
			else
				$result = $result . "1";
		}
		//echo "RESULT:".$result."\n";
		return $result;
	}	
	function init()
	{
		$local = '127.0.0.1';
		$tcp = getprotobyname("tcp");
		$socket = socket_create(AF_INET, SOCK_STREAM, $tcp);
		if(!$socket)
			die('error socket');
		socket_bind($socket, $local, 24568);
		socket_listen($socket);
		return $socket;
	}
	
	function do_handshake($user,$buf)
	{
		$client_connection = $user->socket;
		$data = $buf;
		printf("Received: \n".$data);
		$accept_keyplace=strpos($data, "Sec-WebSocket-Key")+19;
		$accept_key=substr($data, $accept_keyplace,24);
		$new_key_sha1=sha1($accept_key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",TRUE);
		$new_key=base64_encode($new_key_sha1);
		$response_word="HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nSec-WebSocket-Version: 13\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: "
						.$new_key."\r\n\r\n";
		echo $response_word;
		socket_write($client_connection, $response_word,strlen($response_word));
		$user->handshake = true;
		return $client_connection;

	}

	function sendMessage($message,$client)
	{
		//syntax:
		//10000001 0[0001111] [data];
		$pre_bin = "100000010";
		$length = strlen($message);
		$len_bin = sprintf("%07b",$length);
		$data = asc2bin($message);
		$encoded_bin = $pre_bin.$len_bin.$data;
		$encoded_message = bin2asc($encoded_bin);
		echo "sendout:".$encoded_message."\n";
		$result = socket_write($client, $encoded_message, strlen($encoded_message));
		if (FALSE === $result){
			echo "SendMessage Wrong, auto disconnection";
			delete_user($client);
		}
	}

	function receiveMessage($receive){
		//$receive = asc2bin(socket_read($client, 1024));//a binary string;
		$receive = asc2bin($receive);
		if($receive[4]=='1'&&$receive[7]=='0')//gai wei pan bie
			return "CLOSE";
		$whole_length = strlen($receive);
		echo "RECEIVED:".$receive."\n";
	#	if($receive[8]==0)
	#		echo "ERROR FRAME; WARNING: MAYBE SECURITY REASONS";
	#	else
	#	{
			$binary_pointer = 16;
			$mask = array();
			$masked_binary = array();
			$final_result = "";
			for($i=0;$i<4;$i++){
				$temp_mask = substr($receive, $binary_pointer, 8);
				$binary_pointer = $binary_pointer + 8;
				array_push($mask, $temp_mask);
			}//save the mask
			while($binary_pointer<$whole_length){
				$temp_masked_binary = substr($receive, $binary_pointer, 8);
				$binary_pointer = $binary_pointer + 8;
				array_push($masked_binary, $temp_masked_binary);
			}//save the masked data;
			$data_length = count($masked_binary);
			for($i=0;$i<$data_length;$i++){
				$mask_place = $i % 4;
				$bin_result = myXOR($mask[$mask_place],$masked_binary[$i]);
				$final_result .= chr(bindec($bin_result));
			}
			return $final_result;
	#	}
		#return "ERRORCODE";
	}

	//获取时间
	function mt_f (){
		list($usec,$sec) = explode(" ",microtime());
		return ((float)$usec + (float)$sec); //微秒加秒
	}
	function ping($host,$port){
		$time_s = mt_f();
		
		$ip = $host;

		$fp = @fsockopen($host,$port);
		if(!$fp)
			return false;
		$get = "GET / HTTP/1.1\r\nHost:".$host."\r\nConnect:".$port."Close\r\n";
		fputs($fp,$get);
		fclose($fp);
		$time_e = mt_f();
		$time = $time_e - $time_s;
		$time = ceil($time * 1000);
		return 'reply from '.$ip.' time = '.$time."ms\n";
	}

	function traceroute($user)
	{
		$retry = 3;
		$port = 10384;
		$recv_socket = socket_create(AF_INET, SOCK_RAW, getprotobyname('icmp'));
		$send_socket = socket_create(AF_INET, SOCK_DGRAM, getprotobyname('udp'));
		socket_set_option($send_socket, 0, 2, $user->ttl);
		socket_bind($recv_socket, 0, 0);
		$t1 = microtime(true);
		socket_sendto($send_socket, "", 0, 0, $user->ip_addr,$port);
		$r =array($recv_socket);
		$w = $e = array();
		socket_select($r, $w, $e, 1, 0);
		if(count($r)){
			socket_recvfrom($recv_socket, $buf, 512, 0, $recv_addr, $recv_port);
			if (empty($recv_addr)){
				$recv_addr = "*";
				$recv_port = "*";
			} else {
				$recv_name = gethostbyaddr($recv_addr);
			}
			$roundtrip_time = microtime(true) - $t1;
			$result = sprintf ("%3d %-15s %.3f ms %s\n", $user->ttl, $recv_addr, $roundtrip_time, $recv_name);
			echo "result".$result."\n";
		} 
		else {
			$result = sprintf ("%3d (timeout)\n", $user->ttl);
			$user->ttl = -1;
		}
			socket_close($recv_socket);
			socket_close($send_socket);
			$user->ttl++;
			if($recv_addr == $user->ip_addr) 
				$user->ttl = 0;
			return $result;
	}

	function pathping($user){
		global $receive_address;
		$port = 10384;
		$recv_socket = socket_create(AF_INET, SOCK_RAW, getprotobyname('icmp'));
		$send_socket = socket_create(AF_INET, SOCK_DGRAM, getprotobyname('udp'));
		socket_set_option($send_socket, 0, 2, $user->ttl);
		socket_bind($recv_socket, 0, 0);
		socket_sendto($send_socket, "", 0, 0, $user->ip_addr,$port);
		$r =array($recv_socket);
		$w = $e = array();
		socket_select($r, $w, $e, 1, 0);
		if(count($r)){
			socket_recvfrom($recv_socket, $buf, 512, 0, $recv_addr, $recv_port);
			$user->success++;
			$receive_address = $recv_addr;
			echo "SUCCESS\n";
			$result = false;
			echo "result".$result."\n";
		} 
		else {
			$result = false;
		}
			socket_close($recv_socket);
			socket_close($send_socket);
			if($user->package == 0){
				$user->ttl++;
				$user->package = 10;
				
				if($user->success == 0){
					$return = "FAILED To connect next hop!";
					$receive_address = "unknown";
					sendMessage($return,$user->socket);
					$user->ttl = 0;
				}
				else{
					$result = "TO: ".$receive_address."  Success/Package:  ".$user->success."/".$user->package."\n";
					if($receive_address == $user->ip_addr) {
						echo "FINISHED\n";
						$user->ttl = 0;
						$result .= "FINISHED";
					}
				}
				$user->success = 0;
			}

			$user->package--;
			return $result;
	}
	function check_message($client){
		$r = array($client);
		$w = $e = array();
		$num = socket_select($r, $w, $e, 0);
		return $num;
	}
	//MAIN FUNCTION
	
	class User{
		var $id;
		var $socket;
		var $order;
		var $ip_addr;
		var $handshake;
		var $timestamp;
		var $ttl = 1;//0=done;
		var $package = 10;
		var $success = 0;
	}
?>